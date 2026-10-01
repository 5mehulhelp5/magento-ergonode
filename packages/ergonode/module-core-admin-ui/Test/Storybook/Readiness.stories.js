import { expect, userEvent, within } from 'storybook/test';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/readiness.css';

const domainFixtures = {
    connection: {
        label: 'Connection',
        checks: [
            {label: 'Ergonode GraphQL URL is configured.', group: 'read'},
            {label: 'Ergonode read operations are enabled.', group: 'read'},
            {label: 'Ergonode read API key is configured.', group: 'read'},
            {label: 'Ergonode write operations are enabled.', group: 'write'},
            {label: 'Ergonode write API key is configured.', group: 'write'},
            {
                label: 'Load the signed-in account privileges from the Ergonode REST profile.',
                group: 'rest',
                status: 'requires-login'
            },
            {
                label: 'Compare them with the permissions required by the selected operation.',
                group: 'rest',
                status: 'requires-login'
            }
        ]
    },
    languages: {
        label: 'Languages',
        checks: [{
            label: 'Magento Default Values (store ID 0) is mapped to an active Ergonode language.',
            group: 'read'
        }]
    },
    attributes: {
        label: 'Attributes',
        checks: [{
            label: 'Every required Magento product attribute has an Ergonode mapping.',
            group: 'write'
        }]
    },
    categories: {
        label: 'Categories',
        checks: [
            {
                label: 'An active Ergonode category tree is connected to a Magento root category.',
                group: 'read'
            },
            {label: 'Mapped Magento root categories exist.', group: 'read'},
            {
                label: 'Automatic category synchronization can create mappings, or at least one category is mapped for publishing to Ergonode.',
                group: 'write'
            }
        ]
    }
};
const flows = [
    {
        code: 'read',
        label: 'Receive data from Ergonode',
        description: 'Required for importing and updating Magento data from Ergonode. This is sufficient for most integrations.',
        kind: 'Required',
        groups: ['read'],
        domains: ['connection', 'languages', 'categories']
    },
    {
        code: 'write',
        label: 'Send data to Ergonode',
        description: 'Optional add-on for clients that start with catalog data in Magento '
            + 'and want to send it to Ergonode.',
        kind: 'Optional',
        groups: ['write', 'rest'],
        domains: ['connection', 'attributes', 'categories']
    }
];
const result = (domain, overrides = {}) => {
    const fixture = domainFixtures[domain];
    const checks = fixture.checks.map((check, index) => ({
        status: 'ready',
        ...check,
        ...overrides[index]
    }));
    const status = checks.some((check) => check.status === 'blocked')
        ? 'blocked'
        : checks.some((check) => check.status === 'warning') ? 'warning' : 'ready';

    return {code: domain, label: fixture.label, status, checks};
};
const statusLabels = {
    ready: 'passed',
    warning: 'warning',
    blocked: 'failed',
    'not-checked': 'not checked',
    'requires-login': 'requires login'
};

const scenarios = {
    ready: {
        summary: 'The configured Ergonode integration capabilities are ready.',
        domains: Object.keys(domainFixtures).map((domain) => result(domain))
    },
    warning: {
        summary: 'Required synchronization can run; review optional capabilities and non-blocking issues.',
        domains: [
            result('connection', {
                3: {
                    status: 'warning',
                    message: 'Write operations are disabled. Reading from Ergonode remains available.'
                },
                4: {
                    status: 'warning',
                    message: 'The optional write API key is not configured.'
                }
            }),
            result('languages'),
            result('attributes'),
            result('categories', {2: {
                status: 'warning',
                message: 'Automatic Magento category creation is disabled and no category is mapped. Products can be published, but category assignments will not synchronize.',
                href: '#categories'
            }})
        ]
    },
    blocked: {
        summary: 'Resolve the blocking requirements before starting synchronization.',
        domains: [
            result('connection', {2: {
                status: 'blocked',
                message: 'Configure the Ergonode read API key.'
            }}),
            result('languages', {0: {
                status: 'blocked',
                message: 'Map Magento Default Values (store ID 0) to an active Ergonode language.',
                href: '#languages'
            }}),
            result('attributes', {0: {
                status: 'blocked',
                message: '2 required Magento product attribute(s) do not have a complete Ergonode mapping.',
                href: '#attributes'
            }}),
            result('categories', {
                0: {
                    status: 'blocked',
                    message: 'Connect at least one active Ergonode category tree to a Magento root category.',
                    href: '#categories'
                },
                1: {status: 'not-checked'},
                2: {status: 'not-checked'}
            })
        ]
    }
};

function renderChecks(checkList) {
    const checks = document.createElement('ul');

    checks.className = 'ver-readiness-checks';
    checkList.forEach((check) => {
        const item = document.createElement('li');

        item.className = `ver-readiness-check is-${check.status}`;
        item.innerHTML = `
            <div class="ver-readiness-check-summary">
                <p class="ver-readiness-check-label">${check.label}</p>
                <span class="ver-readiness-check-status">
                    ${statusLabels[check.status]}
                </span>
            </div>`;
        const messages = check.messages || (check.message ? [check.message] : []);
        if (messages.length > 0) {
            const issues = messages.map((message) => `<li class="is-${
                check.status === 'blocked' ? 'blocker' : 'warning'
            }"><div class="ver-readiness-issue-copy"><p>${message}</p></div>${
                check.href ? `<a class="veui-button ver-readiness-action" href="${check.href}">Resolve issue</a>` : ''
            }</li>`).join('');

            item.insertAdjacentHTML(
                'beforeend',
                `<ul class="ver-readiness-issues">${issues}</ul>`
            );
        }
        checks.append(item);
    });

    return checks;
}

function renderDomain(domain, checkList) {
    const section = document.createElement('section');
    const header = document.createElement('header');

    section.className = `ver-readiness-domain is-${domain.status}`;
    header.innerHTML = `<h3>${domain.label}</h3>`;
    section.append(header);
    section.append(renderChecks(checkList));

    return section;
}

function renderFlow(flow, scenario) {
    const section = document.createElement('section');
    const grid = document.createElement('div');

    section.className = `veui-panel ver-readiness-flow is-${flow.code}`;
    section.innerHTML = `
        <header class="veui-panel-head ver-readiness-flow-header">
            <span class="ver-readiness-flow-icon" aria-hidden="true"></span>
            <div>
                <div class="ver-readiness-flow-title">
                    <h2>${flow.label}</h2>
                    <span class="ver-readiness-flow-kind">${flow.kind}</span>
                </div>
                <p>${flow.description}</p>
            </div>
        </header>`;
    grid.className = 'ver-readiness-grid';
    flow.domains.forEach((domainCode) => {
        const domain = scenario.domains.find(({code}) => code === domainCode);
        const checks = domain?.checks.filter((check) => flow.groups.includes(check.group)) || [];

        if (domain && checks.length > 0) {
            grid.append(renderDomain(domain, checks));
        }
    });
    section.append(grid);

    return section;
}

function renderReadiness({status = 'blocked'}) {
    const scenario = scenarios[status];
    const workspace = document.createElement('div');
    const content = document.createElement('div');
    const summary = document.createElement('header');
    const flowList = document.createElement('div');

    workspace.className = 'veui-workspace veui-workspace-viewbar ver-readiness';
    workspace.style.height = '640px';
    content.className = 'ver-readiness-content';
    content.tabIndex = 0;
    summary.className = `ver-readiness-summary is-${status}`;
    summary.innerHTML = `
        <span class="ver-readiness-summary-icon" aria-hidden="true"></span>
        <div class="ver-readiness-summary-copy"><span class="ver-readiness-eyebrow">Synchronization Readiness</span><h1>${status}</h1><p>${scenario.summary}</p></div>
        <span class="ver-readiness-status" role="status">${status}</span>`;
    flowList.className = 'ver-readiness-flows';
    flows.forEach((flow) => flowList.append(renderFlow(flow, scenario)));
    content.append(summary, flowList);
    workspace.append(content);

    return workspace;
}

const meta = {
    id: 'ergo-v-033',
    title: 'Ergonode UI/Widoki/Wspólne/ERGO-V-033 · Gotowość synchronizacji/Pełny widok',
    tags: ['autodocs'],
    render: renderReadiness,
    args: {status: 'blocked'},
    argTypes: {
        status: {control: 'select', options: Object.keys(scenarios)}
    }
};

export default meta;

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        Object.keys(scenarios).forEach((status) => grid.append(renderReadiness({status})));

        return grid;
    }
};

export const AkcjaMysza = {
    args: {status: 'blocked'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const action = canvas.getAllByRole('link', {name: 'Resolve issue'})[0];

        await userEvent.click(action);
        await expect(action).toHaveAttribute('href', '#languages');
    }
};

export const AkcjaKlawiatura = {
    args: {status: 'blocked'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const action = canvas.getAllByRole('link', {name: 'Resolve issue'})[0];

        await userEvent.tab();
        await expect(canvasElement.querySelector('.ver-readiness-content')).toHaveFocus();
        await userEvent.tab();
        await expect(action).toHaveFocus();
    }
};
