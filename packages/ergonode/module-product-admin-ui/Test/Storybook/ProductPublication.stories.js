import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule} from '@ergonode-storybook/load-amd-module.js';
import {createNavigation} from '@ergonode-storybook/section-navigation.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/bulk-publish-progress.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '../../view/adminhtml/web/css/publication.css';
import template from '../../view/adminhtml/web/template/publication-grid.html?raw';
import gridSource from '../../view/adminhtml/web/js/publication-grid.js?raw';
import rowActionsSource from '../../view/adminhtml/web/js/product-row-actions.js?raw';
import processSource from '../../view/adminhtml/web/js/publication-process.js?raw';
import productProgressSource from '../../view/adminhtml/web/js/publication-progress.js?raw';
import progressSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/bulk-publish-progress.js?raw';

import mageTemplateSource from '../../../../../lib/web/mage/template.js?raw';
import underscoreSource from '../../../../../lib/web/underscore.js?raw';
import gridPl from '../../i18n/pl_PL.csv?raw';
import gridEn from '../../i18n/en_US.csv?raw';
import coreEn from '@ergonode-modules/CoreAdminUi/i18n/en_US.csv?raw';
import corePl from '@ergonode-modules/CoreAdminUi/i18n/pl_PL.csv?raw';
import consumerEn from '@ergonode-modules/ProductConsumerAdminUi/i18n/en_US.csv?raw';
import consumerPl from '@ergonode-modules/ProductConsumerAdminUi/i18n/pl_PL.csv?raw';
import publisherEn from '@ergonode-modules/ProductPublisherAdminUi/i18n/en_US.csv?raw';
import publisherPl from '@ergonode-modules/ProductPublisherAdminUi/i18n/pl_PL.csv?raw';

const dictionaries = (sources) => Object.fromEntries(sources.flatMap(source =>
    Array.from(source.matchAll(/^"((?:[^"\n]|"")*)","((?:[^"\n]|"")*)"$/gm),
        match => [match[1].replaceAll('""', '"'), match[2].replaceAll('""', '"')])
));
const locales = {en_US:dictionaries([coreEn,gridEn,consumerEn,publisherEn]),pl_PL:dictionaries([corePl,gridPl,consumerPl,publisherPl])};
const underscore = loadAmdModule(underscoreSource.replace("define('underscore', factory)", 'define([], factory)'));
const renderTemplate = loadAmdModule(mageTemplateSource, {underscore});

const thumbnail = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" rx="6" fill="#eef2f6"/><path d="M15 12h18l5 9-7 4v13H17V25l-7-4z" fill="#a4b5c8"/></svg>');
function render(args) {
    const translate = text => locales[args.locale || 'pl_PL'][text] || text;
    const container = document.createElement('div');
    const workspace = document.createElement('div');
    workspace.className = 'veui-workspace vepp-workspace';
    const toolbar = document.createElement('div');
    toolbar.className = 'veui-toolbar vea-viewbar';
    toolbar.dataset.role = 'viewbar';
    toolbar.append(createNavigation({currentSection: 'products', categoryOptionsAvailable: true}));
    workspace.append(toolbar);
    container.append(workspace);
    const products = Array.from({length: args.state === 'empty' ? 0 : 61}, (_, i) => ({
        product_id: i + 1, sku: `SKU-${String(i+1).padStart(3,'0')}`, name: `Koszulka bawełniana ${i+1}`,
        ergonode_sku: i % 3 ? `ERGO-${i+1}` : '', attribute_set_id: i%2 ? 5 : 4, attribute_set_name: i%2 ? 'Odzież' : 'Default', type_id: i%5 ? 'simple' : 'virtual',
        thumbnail, product_url: '#product'
    }));
    const published = [];
    container.dataset.publicationFixture = '';
    container.published = published;
    function jquery(element) {
        return {element, modal(action) {
            this.shell.hidden = action === 'closeModal';
            element.style.display = action === 'closeModal' ? 'none' : 'grid';
            if (action === 'closeModal' && this.options.closed) { this.options.closed(); }
            if (action === 'openModal' && this.options.focus) { element.querySelector(this.options.focus)?.focus(); }
            return this;
        }};
    }
    function matching(search, criteria = {}) {
        return products.filter(p => `${p.sku} ${p.name} ${p.product_id}`.includes(search || '') &&
            Object.entries(criteria).every(([key, value]) => ['sort', 'direction'].includes(key) ||
                (['product_id','attribute_set_id','type_id'].includes(key) ? String(p[key]) === value : String(p[key]).includes(value))));
    }
    jquery.ajax = ({url, data}) => {
        let done, fail;
        const chain = {done(callback) {done=callback; return chain;}, fail(callback) {fail=callback; return chain;}};
        const respond = () => {
            if (args.state === 'connectionError') { fail(); return; }
            const filtered = matching(data.search, data.criteria);
            const sort = data.criteria?.sort || 'product_id';
            filtered.sort((a, b) => (typeof a[sort] === 'number' ? a[sort] - b[sort] : a[sort].localeCompare(b[sort])) *
                (data.criteria?.direction === 'DESC' ? -1 : 1));
            const page = Math.max(1, Math.min(data.page, Math.ceil(filtered.length / data.page_size)));
            if (url === 'data') {
                done({success:true, data:{total:filtered.length, page, items:filtered.slice((page-1)*data.page_size, page*data.page_size),
                    filter_options:{
                        attribute_set_id:[{value:'4',label:'Default'},{value:'5',label:'Odzież'}],
                        type_id:[{value:'simple',label:'simple'},{value:'virtual',label:'virtual'}]
                    },
                    actions: (args.directions || ['publish']).map(code => ({code, url:code, requires_mapping:code==='import',
                        preflight: args.preflight ? {component:'fixture/preflight'} : null,
                        label:code==='import'?'Download from Ergonode':'Send to Ergonode',
                        button_label:code==='import'?'Download':'Send', primary:code==='import',
                        icon_class:code==='import'?'veui-refresh-ergonode-icon':'veui-create-ergonode-icon',
                        ready:args.state !== 'configuration', message:'Skonfiguruj połączenie z uprawnieniem zapisu do Ergonode.', configuration_url:'#configuration',
                        progress:{statusLabels:{processing:translate(code==='import'?'Pobieranie':'Wysyłanie'),success:translate(code==='import'?'Pobrano':'Opublikowano'),failed:translate('Failed')},
                            title:translate(code==='import'?'Pobieranie danych produktów do Magento':'Publikacja produktów do Ergonode'),unit:translate('produktów')}
                    }))}});
            } else if (url === 'snapshot') {
                const selection = JSON.parse(data.selection);
                const ids = selection.all ? matching(selection.search, selection.criteria).map(p=>p.product_id).filter(id=>!selection.excluded.includes(id)) : selection.selected;
                done({success:true, ids});
            } else {
                const ids = JSON.parse(data.ids);
                published.push(ids);
                const items = ids.map(id=>({product_id:id, code:`SKU-${String(id).padStart(3,'0')}`,
                    status:args.state==='results' ? (['success','failed','warning','unconfirmed'][id-1] || 'success') :
                        (args.state==='partial' && id===2 ? 'failed':'success'),
                    message:args.state==='results' && id===3 ? 'Product "SKU-003": omitted attribute "product_type_pim" in all languages; Magento option ID(s) without Ergonode mapping: 7788.\nProduct "SKU-003": omitted attribute "navireo_synchro" in all languages; Magento option ID(s) without Ergonode mapping: 1.' :
                        (args.state==='results' && id>1 && id<5 ? 'Attribute: sku. Language: en_GB. Code: VALIDATION_ERROR. <script>alert(1)</script>' :
                        (args.state==='partial' && id===2 ? 'Brak wymaganego szablonu w Ergonode.':'')),
                    publication_at:'2026-09-10 18:45:00'}));
                if (args.state === 'transportError') { fail(); return; }
                done({success:true, items:items.reverse()});
            }
        };
        if (args.state === 'loading') { return chain; }
        if (args.holdPublication && url === 'publish') { container.releasePublication=respond; return chain; }
        if (container.holdNextRequest && url !== 'publish') {
            container.holdNextRequest = false;
            container.releaseRequest = respond;
        } else {
            window.setTimeout(respond, url === 'publish' ? 350 : 10);
        }
        return chain;
    };
    const modal = (options,widget)=>{
        const shell=document.createElement('section');
        shell.className=`modal-popup ${options.modalClass}`; shell.hidden=true;
        shell.setAttribute('role','dialog'); shell.setAttribute('aria-label',options.title);
        const inner=document.createElement('div'); inner.className='modal-inner-wrap';
        const header=document.createElement('header'); header.className='modal-header';
        const heading=document.createElement('h2'); heading.className='modal-title'; heading.textContent=options.title;
        const close=document.createElement('button'); close.type='button'; close.className='action-close';
        close.dataset.role='closeBtn'; close.textContent='×'; close.setAttribute('aria-label','Close');
        header.append(heading,close);
        const content=document.createElement('div'); content.className='modal-content'; content.append(widget.element);
        inner.append(header,content); shell.append(inner); container.append(shell);
        widget.shell=shell; widget.options=options;
        // Magento binds the close handler through this selector; false must leave it unbound.
        if (options.modalCloseBtn !== false) {
            shell.querySelector(options.modalCloseBtn || '[data-role="closeBtn"]').addEventListener('click',
                options.modalCloseBtnHandler || (()=>widget.modal('closeModal')));
        }
        shell.addEventListener('keydown',event=>{
            if(event.key==='Escape') { options.keyEventHandlers?.escapeKey?.(); }
        });
    };
    const baseProgress=loadAmdModule(progressSource, {jquery, 'mage/translate':translate,
        'Magento_Ui/js/modal/modal':modal
    });
    const createProgress=loadAmdModule(productProgressSource, {'Ergonode_CoreAdminUi/js/bulk-publish-progress':baseProgress, 'mage/translate':translate});
    const createProcess=loadAmdModule(processSource,{'mage/translate':translate});
    const grid=loadAmdModule(gridSource, {jquery,
        require: (modules, resolve) => resolve(() => ({ensure: async () => args.preflight !== 'cancel'})),
        'Ergonode_ProductAdminUi/js/product-row-actions':loadAmdModule(rowActionsSource, {'mage/translate':translate}),
        'mage/translate':translate,
        'mage/template':renderTemplate,
        'text!Ergonode_ProductAdminUi/template/publication-grid.html':template,
        'Ergonode_ProductAdminUi/js/publication-progress':createProgress,
        'Ergonode_ProductAdminUi/js/publication-process':createProcess
    });
    container.initializeGrid = () => grid({dataUrl:'data',snapshotUrl:'snapshot'},workspace);
    if (!args.deferInitialization) {
        container.initializeGrid();
    }
    return container;
}
export default {
    id: 'ergo-v-043',title:'Ergonode UI/Widoki/Produkty/ERGO-V-043 · Publikacja produktów/Pełny widok',render,args:{state:'ready',locale:'pl_PL'},
    argTypes:{state:{control:'select',options:['ready','empty','configuration','connectionError','partial','loading','results','transportError']}},
    parameters:{a11y:{test:'error'}}};
export const Playground = {play:async({canvasElement})=>{
    const c = within(canvasElement);
    await c.findByRole('checkbox', {name:'Zaznacz SKU-001'});
    const toolbar = canvasElement.querySelector('.vea-viewbar');
    const panel = canvasElement.querySelector('.vepp-panel');
    await expect(within(toolbar).getByRole('navigation', {name:'Ergonode sections'})).toBeVisible();
    await expect(canvasElement.querySelector('.vepp-head')).not.toBeInTheDocument();
    await expect(panel.getBoundingClientRect().width).toBeCloseTo(toolbar.getBoundingClientRect().width, 0);
    await expect(panel.getBoundingClientRect().left).toBeCloseTo(toolbar.getBoundingClientRect().left, 0);
    await userEvent.click(within(toolbar).getByRole('button', {name:'Attribute options'}));
    await expect(within(toolbar).getByRole('link', {name:'List', exact:true})).toHaveAttribute('aria-current', 'page');
    await userEvent.keyboard('[Escape]');
}};
export const CancelledPreflight = {args:{preflight:'cancel'},play:async({canvasElement})=>{
    const canvas = within(canvasElement);
    await canvas.findByRole('checkbox', {name:'Zaznacz SKU-001'});
    await userEvent.click(canvasElement.querySelector('[data-product-id="1"][data-product-action]'));
    await waitFor(() => expect(canvasElement.querySelector('[data-product-id="1"][data-product-action]')).toBeEnabled());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([]);
    await expect(canvas.queryByRole('dialog')).not.toBeInTheDocument();
}};
export const Wczytywanie = {args:{state:'loading'}};
export const PierwszyRender = {args:{deferInitialization:true},play:async({canvasElement})=>{
    const fixture = canvasElement.querySelector('[data-publication-fixture]');
    const toolbar = fixture.querySelector('[data-role="viewbar"]');
    const navigation = within(toolbar).getByRole('navigation', {name:'Ergonode sections'});
    const link = within(navigation).getByRole('link', {name:'Languages',exact:true});
    const before = toolbar.getBoundingClientRect().toJSON();
    await expect(before.height).toBe(68);
    link.focus();
    fixture.initializeGrid();
    await within(fixture).findByRole('checkbox', {name:'Zaznacz SKU-001'});
    await expect(fixture.querySelector('[data-role="viewbar"]')).toBe(toolbar);
    await expect(toolbar.getBoundingClientRect().toJSON()).toEqual(before);
    await expect(link).toHaveFocus();
}};
export const PustyGrid = {args:{state:'empty'}};
export const BrakKonfiguracji = {args:{state:'configuration'}};
export const BladPolaczenia = {args:{state:'connectionError'}};
export const ZaznaczenieMiedzyStronami = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await userEvent.click(await c.findByRole('checkbox',{name:'Zaznacz SKU-001'}));
    await userEvent.click(c.getByRole('button',{name:'Następna'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-021'});
    await expect(c.getByText('Zaznaczono: 1')).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz SKU-021'}));
    await expect(c.getByText('Zaznaczono: 60 (wszystkie wyniki)')).toBeVisible();
}};
export const PojedynczaPublikacja = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await userEvent.click(await c.findByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    await waitFor(()=>expect(canvasElement.querySelector('[data-role="publish-progress-close"]')).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([[1]]);
    await userEvent.click(c.getByRole('button',{name:'Zamknij'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Akcje produktu SKU-001'})).not.toHaveAttribute('aria-disabled','true'));
    await userEvent.click(c.getByRole('button',{name:'Akcje produktu SKU-001'}));
    const view = await c.findByRole('link',{name:'Zobacz produkt SKU-001 (nowa karta)'});
    await expect(view).toHaveAttribute('target','_blank');
    await expect(view).toHaveAttribute('href','#product');
    await expect(view).toHaveAttribute('title','Zobacz produkt');
    await expect(view).toHaveTextContent(/^Zobacz$/);
    await expect(view.querySelector('.vepp-view-icon')).toBeVisible();
}};
export const PauzaIWznowienie = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await userEvent.click(await c.findByRole('button',{name:'Wstrzymaj'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Wznów'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published.length).toBeLessThanOrEqual(1);
    await userEvent.click(c.getByRole('button',{name:'Wznów'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published.flat()).toHaveLength(61);
}};
export const Zatrzymanie = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await userEvent.click(await c.findByRole('button',{name:'Zatrzymaj'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published.flat().length).toBeLessThanOrEqual(50);
}};
export const BledyProduktow = {args:{state:'partial'},play:async({canvasElement})=>{
    const c=within(canvasElement);
    const checkbox=await c.findByRole('checkbox',{name:'Zaznacz SKU-002'});
    checkbox.focus(); await userEvent.keyboard('[Space]');
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await expect(within(canvasElement.querySelector('[data-role="publish-errors"]')).getByText('Brak wymaganego szablonu w Ergonode.')).toBeVisible();
}};

export const FiltrySortowanieIPaginacja = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await expect(c.getAllByText('Default')[0]).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Ostatnia strona'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-061'});
    await expect(c.getByText('61–61 z 61 produktów')).toBeVisible();
    await userEvent.clear(c.getByRole('spinbutton',{name:'Numer strony'}));
    await userEvent.type(c.getByRole('spinbutton',{name:'Numer strony'}),'2{Enter}');
    await c.findByRole('checkbox',{name:'Zaznacz SKU-021'});
    await userEvent.click(c.getByRole('button',{name:'SKU',exact:true}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await userEvent.click(c.getByRole('button',{name:'SKU',exact:true}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-061'});
    await expect(c.getByRole('columnheader',{name:'SKU',exact:true})).toHaveAttribute('aria-sort','descending');
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Filtr: Zestaw atrybutów'}),'5');
    await userEvent.click(c.getByRole('button',{name:'Zastosuj filtry'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-060'});
    await expect(await c.findByText('1–20 z 30 produktów')).toBeVisible();
    c.getByRole('button',{name:'Opcje zaznaczania'}).focus();
    await userEvent.keyboard('[ArrowDown][Enter]');
    await expect(c.getByText('Zaznaczono: 20')).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Odznacz zaznaczone'}));
    await expect(c.getByText('Zaznaczono: 0')).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published.flat()).toEqual(Array.from({length:30},(_,i)=>(i+1)*2));
    await userEvent.click(c.getByRole('button',{name:'Zamknij'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Wyczyść filtry'})).toBeEnabled());
    await userEvent.click(c.getByRole('button',{name:'Wyczyść filtry'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-061'});
    await expect(c.getByText('Zaznaczono: 0')).toBeVisible();
    await expect(c.getByText('1–20 z 61 produktów')).toBeVisible();
}};

export const WczytywanieBezPrzesuwaniaGrida = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    const fixture=canvasElement.querySelector('[data-publication-fixture]');
    const table=canvasElement.querySelector('.vepp-table');
    const row=await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    const pagination=c.getByRole('navigation',{name:'Paginacja produktów'});
    const positions=()=>[table.getBoundingClientRect().top,row.getBoundingClientRect().top,pagination.getBoundingClientRect().top];
    const before=positions();
    fixture.holdNextRequest=true;
    await userEvent.click(c.getByRole('button',{name:'Zastosuj filtry',exact:true}));
    await expect(c.getByText('Wczytuję produkty…',{exact:true})).toBeVisible();
    await expect(row).toBeDisabled();
    await expect(positions()).toEqual(before);
    await expect(canvasElement.querySelector('[data-role="message"]')).not.toBeVisible();
    fixture.releaseRequest();
    await waitFor(()=>expect(canvasElement.querySelector('[data-role="loading"]')).not.toBeVisible());
    await expect(table.getBoundingClientRect().top).toBe(before[0]);
    await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz SKU-001'}));
    fixture.holdNextRequest=true;
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await expect(c.getByText('Przygotowuję listę produktów…',{exact:true})).toBeVisible();
    await expect(table.getBoundingClientRect().top).toBe(before[0]);
    fixture.releaseRequest();
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await waitFor(()=>expect(canvasElement.querySelector('[data-role="loading"]')).not.toBeVisible());
}};


export const WynikiPublikacji = {args:{state:'results',holdPublication:true},play:async({canvasElement})=>{
    const c=within(canvasElement);
    const fixture=canvasElement.querySelector('[data-publication-fixture]');
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await expect(c.queryByRole('columnheader',{name:'Publikacja',exact:true})).toBeNull();
    await expect(c.getAllByRole('columnheader')).toHaveLength(9);
    for (const id of ['001','002','003','004']) {
        await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz SKU-'+id}));
    }
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await waitFor(()=>expect(typeof fixture.releasePublication).toBe('function'));
    const current=canvasElement.querySelector('[data-role="publish-current-items"]');
    await expect(within(current).getAllByText('Wysyłanie')).toHaveLength(4);
    fixture.releasePublication();
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    for (const [id,status] of [[1,'success'],[2,'failed'],[3,'warning'],[4,'unconfirmed']]) {
        await expect(current.querySelector('[data-product-id="'+id+'"]').dataset.publicationStatus).toBe(status);
    }
    await expect(canvasElement.querySelector('[data-role="publish-success-count"]')).toHaveTextContent('1');
    await expect(canvasElement.querySelector('[data-role="publish-warning-count"]')).toHaveTextContent('2');
    const failed=current.querySelector('[data-product-id="2"]');
    failed.querySelector('summary').focus();
    await expect(failed.querySelector('summary')).toHaveFocus();
    await userEvent.click(failed.querySelector('summary'));
    await expect(within(failed).getByText(/Attribute: sku. Language: en_GB/)).toBeVisible();
    const warning=current.querySelector('[data-product-id="3"]');
    await userEvent.click(warning.querySelector('summary'));
    const warningMessage=warning.querySelector('.vepp-progress-product-message');
    await expect(warningMessage).toBeVisible();
    await expect(warningMessage.textContent.split('\n')).toHaveLength(2);
    await expect(warningMessage.textContent).toContain('product_type_pim');
    await expect(warningMessage.textContent).toContain('navireo_synchro');
    await expect(failed.querySelector('script')).toBeNull();
    await expect(canvasElement.querySelectorAll('[role="dialog"]')).toHaveLength(1);
}};
export const HistoriaPaczek = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    const history=canvasElement.querySelector('.vepp-progress-history');
    await userEvent.click(within(history).getByText('Paczka 1 z 2 (50 produktów)'));
    await expect(within(history).getAllByText('Opublikowano')).toHaveLength(50);
    await expect(within(history).getAllByText('Opublikowano')[0]).toBeVisible();
    await expect(within(canvasElement.querySelector('[data-role="publish-current-items"]')).getAllByText('Opublikowano')).toHaveLength(11);
    await userEvent.click(c.getByRole('button',{name:'Zamknij'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Wyślij do Ergonode: SKU-001'})).toBeEnabled());
    await userEvent.click(c.getByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    await expect(history).not.toBeVisible();
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
}};
export const NiepotwierdzonaPaczka = {args:{state:'transportError'},play:async({canvasElement})=>{
    const c=within(canvasElement);
    await userEvent.click(await c.findByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    const current=canvasElement.querySelector('[data-role="publish-current-items"]');
    await expect(within(current).getByText('Wynik niepotwierdzony')).toBeVisible();
    await userEvent.click(current.querySelector('summary'));
    await expect(within(current).getByText(/Wynik bieżącej paczki może być częściowy/)).toBeVisible();
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([[1]]);
}};

export const AkcjeZbiorcze = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    const actions=c.getByRole('combobox',{name:'Akcje'});
    await expect(actions).toBeDisabled();
    await expect(actions).toHaveDisplayValue('Akcje');
    await expect(actions.getBoundingClientRect().right).toBeLessThan(c.getByRole('searchbox',{name:'Szukaj produktu'}).getBoundingClientRect().left);
    await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz SKU-001'}));
    await expect(actions).toBeEnabled();
    await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz SKU-001'}));
    await expect(actions).toBeDisabled();
    await userEvent.click(c.getByRole('checkbox',{name:'Zaznacz produkty na tej stronie'}));
    await userEvent.selectOptions(actions,'publish');
    await expect(actions).toBeDisabled();
    await expect(actions).toHaveDisplayValue('Akcje');
    await waitFor(()=>expect(c.getByRole('button',{name:'Zamknij'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([Array.from({length:20},(_,i)=>i+1)]);
    await userEvent.click(c.getByRole('button',{name:'Zamknij'}));
    await waitFor(()=>expect(actions).toBeEnabled());
}};
export const TlumaczenieAkcji = {args:{locale:'en_US',directions:['publish','import']},play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Select SKU-001'});
    for (const name of ['Apply filters','Clear filters','Name','Product ID','Next','Last page']) {
        await expect(c.getByRole('button',{name,exact:true})).toBeVisible();
    }
    await expect(c.getByRole('searchbox',{name:'Search products'})).toHaveAttribute('placeholder','Search by SKU, name or ID…');
    await expect(c.getByRole('combobox',{name:'Filter: Product type'})).toHaveDisplayValue('All product types');
    await expect(c.getByRole('combobox',{name:'Filter: Attribute set'})).toHaveDisplayValue('All attribute sets');
    await expect(c.getByText('1–20 of 61 products')).toBeVisible();
    await expect(c.getByText('Selected: 0')).toBeVisible();
    await expect(c.getByRole('combobox',{name:'Actions'})).toHaveDisplayValue('Actions');
    await expect(await c.findByRole('option',{name:'Send to Ergonode'})).toBeInTheDocument();
    await userEvent.click(c.getByRole('button',{name:'Product actions SKU-002'}));
    const menu = within(await c.findByRole('group',{name:'Product options SKU-002'}));
    const send = menu.getByRole('button',{name:'Send to Ergonode: SKU-002'});
    const download = menu.getByRole('button',{name:'Download from Ergonode: SKU-002'});
    await expect(menu.getByRole('link',{name:'View product SKU-002 (new tab)'})).toHaveTextContent('View');
    await expect(send).toHaveTextContent(/^Send$/);
    await expect(send).toHaveAttribute('title','Send to Ergonode');
    await expect(send.querySelector('.veui-create-ergonode-icon')).toBeVisible();
    await expect(download).toHaveTextContent(/^Download$/);
    await expect(download).toHaveAttribute('title','Download from Ergonode');
    await expect(download.querySelector('.veui-refresh-ergonode-icon')).toBeVisible();
    await expect(c.getByRole('button',{name:'Download from Ergonode: SKU-001'})).toBeDisabled();
    download.focus();
    await userEvent.keyboard('[Enter]');
    await waitFor(()=>expect(c.getByRole('button',{name:'Close'})).toBeVisible());
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([[2]]);
    await expect(c.getByRole('dialog',{name:'Download product data to Magento'})).toBeVisible();
    await expect(c.getByText('Downloaded')).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Close'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Apply filters'})).toBeEnabled());
    await userEvent.type(c.getByRole('searchbox',{name:'Search products'}),'no-such-product{Enter}');
    await expect(await c.findByText('No products match your filters.')).toBeVisible();
    await expect(c.getByText('0 products')).toBeVisible();
}};

export const EnglishConnectionError = {args:{locale:'en_US',state:'connectionError'},play:async({canvasElement})=>{
    await expect(await within(canvasElement).findByText('Unable to receive a response. Check your connection and admin session.')).toBeVisible();
}};

export const EnglishLoading = {args:{locale:'en_US',state:'loading'},play:async({canvasElement})=>{
    await expect(await within(canvasElement).findByText('Loading products…')).toBeVisible();
}};

export const ListyFiltrow = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    const sets=c.getByRole('combobox',{name:'Filtr: Zestaw atrybutów'});
    const types=c.getByRole('combobox',{name:'Filtr: Typ produktu'});
    const heading=c.getByRole('columnheader',{name:'Zestaw atrybutów'});
    await expect(sets.getBoundingClientRect().top-heading.getBoundingClientRect().bottom).toBeGreaterThanOrEqual(10);
    await userEvent.selectOptions(sets,'5');
    await userEvent.selectOptions(types,'virtual');
    await userEvent.click(c.getByRole('button',{name:'Zastosuj filtry'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-006'});
    await expect(await c.findByText('1–6 z 6 produktów')).toBeVisible();
    await expect(sets).toHaveDisplayValue('Odzież');
    await expect(types).toHaveValue('virtual');
    await expect(within(sets).getByRole('option',{name:'Default'})).toBeInTheDocument();
    await userEvent.type(c.getByRole('searchbox',{name:'Szukaj produktu'}),'nieistniejący{Enter}');
    await c.findByText('Brak produktów spełniających kryteria.');
    await expect(within(types).getByRole('option',{name:'simple'})).toBeInTheDocument();
    await userEvent.click(c.getByRole('button',{name:'Wyczyść filtry'}));
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await expect(sets).toHaveValue('');
    await expect(types).toHaveValue('');
    await expect(c.getByRole('searchbox',{name:'Szukaj produktu'})).toHaveValue('');
}};

export const ZamykanieKrzyzykiem = {play:async({canvasElement})=>{
    const c=within(canvasElement);
    await userEvent.click(await c.findByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    const close=await c.findByRole('button',{name:'Zamknij okno'});
    const before=close.getBoundingClientRect();
    const headerBefore=close.parentElement.getBoundingClientRect();
    await userEvent.hover(close);
    close.focus();
    const after=close.getBoundingClientRect();
    const headerAfter=close.parentElement.getBoundingClientRect();
    await expect(after.x-headerAfter.x).toBe(before.x-headerBefore.x);
    await expect(after.y-headerAfter.y).toBe(before.y-headerBefore.y);
    await userEvent.click(close);
    await expect(c.queryByRole('dialog')).not.toBeInTheDocument();
    await waitFor(()=>expect(c.getByRole('button',{name:'Wyślij do Ergonode: SKU-001'})).toBeEnabled());
    await userEvent.click(c.getByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    (await c.findByRole('button',{name:'Zamknij okno'})).focus();
    await userEvent.keyboard('{Escape}');
    await expect(c.queryByRole('dialog')).not.toBeInTheDocument();
}};
export const ZamknieciePodczasWysylki = {args:{holdPublication:true},play:async({canvasElement})=>{
    const c=within(canvasElement);
    const fixture=canvasElement.querySelector('[data-publication-fixture]');
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await userEvent.click(c.getByRole('button',{name:'Opcje zaznaczania'}));
    await userEvent.click(c.getByRole('button',{name:'Zaznacz wszystkie wyniki'}));
    await userEvent.selectOptions(c.getByRole('combobox',{name:'Akcje'}),'publish');
    await waitFor(()=>expect(fixture.releasePublication).toBeInstanceOf(Function));
    const pause=c.getByRole('button',{name:'Wstrzymaj'});
    const stop=c.getByRole('button',{name:'Zatrzymaj'});
    await expect(pause.closest('.modal-footer')).not.toBeNull();
    await expect(stop).toBeEnabled();
    await userEvent.click(c.getByRole('button',{name:/Zatrzymaj wysyłkę i zamknij/}));
    await expect(c.getByText(/Kończę bieżącą paczkę/)).toBeVisible();
    await expect(stop).toBeDisabled();
    fixture.releasePublication();
    await waitFor(()=>expect(c.queryByRole('dialog')).not.toBeInTheDocument());
    await expect(fixture.published).toHaveLength(1);
    await expect(fixture.published[0]).toHaveLength(50);
}};

export const BladPodczasZamykania = {args:{state:'transportError',holdPublication:true},play:async({canvasElement})=>{
    const c=within(canvasElement);
    const fixture=canvasElement.querySelector('[data-publication-fixture]');
    await userEvent.click(await c.findByRole('button',{name:'Wyślij do Ergonode: SKU-001'}));
    await waitFor(()=>expect(fixture.releasePublication).toBeInstanceOf(Function));
    await userEvent.click(c.getByRole('button',{name:/Zatrzymaj wysyłkę i zamknij/}));
    fixture.releasePublication();
    await c.findByRole('button',{name:'Zamknij okno'});
    await expect(c.getByRole('dialog')).toBeVisible();
    await expect(within(canvasElement.querySelector('[data-role="publish-errors"]'))
        .getByText(/Wynik bieżącej paczki może być częściowy/)).toBeVisible();
    await userEvent.click(c.getByRole('button',{name:'Zamknij okno'}));
    await expect(c.queryByRole('dialog')).not.toBeInTheDocument();
}};

export const TylkoPobieranie = {args:{directions:['import']},play:async({canvasElement})=>{
    const c=within(canvasElement);
    const unavailable=await c.findByRole('button',{name:'Pobierz z Ergonode: SKU-001'});
    await expect(unavailable).toBeDisabled();
    await expect(c.queryByRole('button',{name:'Wyślij do Ergonode: SKU-002'})).toBeNull();
    await userEvent.click(c.getByRole('button',{name:'Pobierz z Ergonode: SKU-002'}));
    await c.findByText('Pobrano');
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([[2]]);
}};
export const BezModulowKierunkowych = {args:{directions:[]},play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    await expect(c.getByRole('combobox',{name:'Akcje'})).toBeDisabled();
    await userEvent.click(c.getByRole('button',{name:'Następna'}));
    await c.findByRole('link',{name:'Zobacz produkt SKU-021 (nowa karta)'});
}};
export const ObaKierunki = {args:{directions:['publish','import']},play:async({canvasElement})=>{
    const c=within(canvasElement);
    await userEvent.click(await c.findByRole('button',{name:'Akcje produktu SKU-002'}));
    await userEvent.click(await c.findByRole('button',{name:'Wyślij do Ergonode: SKU-002'}));
    await userEvent.click(await c.findByRole('button',{name:'Zamknij'}));
    await waitFor(()=>expect(c.getByRole('button',{name:'Pobierz z Ergonode: SKU-002'})).toBeEnabled());
    await userEvent.click(c.getByRole('button',{name:'Pobierz z Ergonode: SKU-002'}));
    await c.findByText('Pobrano');
    const ids=Array.from(canvasElement.querySelectorAll('[id]'),node=>node.id);
    await expect(new Set(ids).size).toBe(ids.length);
    await expect(canvasElement.querySelector('[data-publication-fixture]').published).toEqual([[2],[2]]);
}};

export const KolumnyIAkcje = {args:{directions:['publish','import']},play:async({canvasElement})=>{
    const c=within(canvasElement);
    await c.findByRole('checkbox',{name:'Zaznacz SKU-001'});
    const headers=Array.from(canvasElement.querySelectorAll('.vepp-table thead tr:first-child th'));
    await expect(headers.map(header=>header.querySelector('[data-sort]')?.dataset.sort || header.textContent.trim()).slice(1))
        .toEqual(['product_id','Miniatura','name','sku','ergonode_sku','attribute_set_name','type_id','Akcje']);
    await expect(headers[1].getBoundingClientRect().width).toBeLessThanOrEqual(95);
    const row=canvasElement.querySelector('[data-role="rows"] tr');
    await expect(row.cells[1]).toHaveTextContent('1');
    await expect(row.cells[2].querySelector('img')).toBeVisible();
    await expect(row.cells[3]).toHaveTextContent('Koszulka bawełniana 1');
    await expect(row.cells[4]).toHaveTextContent('SKU-001');
    const indicators=()=>Array.from(canvasElement.querySelectorAll('[data-sort-indicator]')).filter(node=>node.textContent);
    await expect(indicators()).toHaveLength(1);
    await userEvent.click(c.getByRole('button',{name:'Nazwa',exact:true}));
    await waitFor(()=>expect(headers[3]).toHaveAttribute('aria-sort','ascending'));
    await expect(indicators()).toHaveLength(1);
    await expect(indicators()[0]).toHaveTextContent('↑');
    await userEvent.click(c.getByRole('button',{name:'Nazwa',exact:true}));
    await waitFor(()=>expect(headers[3]).toHaveAttribute('aria-sort','descending'));
    await expect(indicators()).toHaveLength(1);
    await expect(indicators()[0]).toHaveTextContent('↓');
    await userEvent.click(c.getByRole('button',{name:'ID produktu',exact:true}));
    const primary=await c.findByRole('button',{name:'Pobierz z Ergonode: SKU-002'});
    await expect(primary).toHaveTextContent('Pobierz');
    const toggle=c.getByRole('button',{name:'Akcje produktu SKU-002'});
    toggle.focus();
    await userEvent.keyboard('[ArrowDown]');
    const menu=await c.findByRole('group',{name:'Opcje produktu SKU-002'});
    await expect(within(menu).getAllByRole('button')).toHaveLength(2);
    await expect(within(menu).getAllByRole('link')).toHaveLength(1);
    await expect(within(menu).getByRole('button',{name:'Pobierz z Ergonode: SKU-002'})).toHaveFocus();
    await userEvent.keyboard('[ArrowDown]');
    await expect(within(menu).getByRole('button',{name:'Wyślij do Ergonode: SKU-002'})).toHaveFocus();
    await userEvent.keyboard('[Escape]');
    await expect(menu).not.toBeVisible();
    await expect(toggle).toHaveFocus();
    await userEvent.click(toggle);
    await waitFor(()=>expect(menu).toBeVisible());
    await userEvent.click(c.getByRole('button',{name:'Akcje produktu SKU-003'}));
    await expect(menu).not.toBeVisible();
    const nextMenu=await c.findByRole('group',{name:'Opcje produktu SKU-003'});
    within(nextMenu).getByRole('button',{name:'Pobierz z Ergonode: SKU-003'}).focus();
    await userEvent.keyboard('[Escape]');
    await waitFor(()=>expect(c.queryByRole('group',{name:'Opcje produktu SKU-003'})).toBeNull());
}};
