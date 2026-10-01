const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/language-mapping.js'), 'utf8');
const matchSource = source.slice(source.indexOf('    function findAutoMatches('), source.indexOf('    function oppositeSource('));

function matcher() {
    return vm.runInNewContext(`(function () {${matchSource}; return findAutoMatches;})()`, {
        Map,
        text: {normalize: (value) => value.trim().toLowerCase()},
        isCardActive: (card) => card.active,
        cardPayload: (card) => ({code: card.code}),
    });
}

function card(code, active = true) {
    return {code, active, getAttribute: () => code};
}

function root(languages, stores) {
    return {queries: 0, querySelectorAll(selector) {
        this.queries++;
        return selector.includes('data-source="ergo"') ? languages : stores;
    }};
}

test('exact locale wins over the first prefix match and inactive languages are skipped', () => {
    const view = root([card('en_AU'), card('en_GB', false), card('en_US')], [card('en-US'), card('en_CA'), card('pl_PL')]);
    const matches = matcher()(view);
    assert.deepEqual(Array.from(matches, (match) => [match.language.code, match.store.code]), [
        ['en_US', 'en-US'], ['en_AU', 'en_CA'],
    ]);
});

test('matching scans each source list once even for a large batch', () => {
    const view = root([card('pl_PL')], Array.from({length: 1000}, () => card('pl_PL')));
    const matches = matcher()(view);
    assert.equal(matches.length, 1000);
    assert.equal(view.queries, 2);
    assert.equal(matches[0].languageCard.code, 'pl_PL');
    assert.equal(matches[0].storeCard, view.querySelectorAll('magento')[0]);
});

test('bulk matching defers panel recalculation and uses known card references', () => {
    const start = source.indexOf('            function addMapping(');
    const add = source.slice(start, source.indexOf('            function removeMappingRow(', start));
    const batchStart = source.indexOf("scope.delegate('click', '[data-role=\"auto-match\"]'");
    const batch = source.slice(batchStart, source.indexOf("scope.delegate('click', '[data-role=\"refresh-ergonode\"]'", batchStart));
    assert.match(add, /batchMatch \? batchMatch\.storeCard/);
    assert.match(add, /if \(!batchMatch\) \{\s*updateState/);
    assert.match(batch, /addMapping\(match\.language, match\.store, match\)/);
    assert.equal((batch.match(/updateState\(/g) || []).length, 1);
    assert.equal((batch.match(/autosave\.schedule\(/g) || []).length, 1);
});
