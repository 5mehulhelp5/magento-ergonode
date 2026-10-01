const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/language-mapping.js'), 'utf8');
function section(start, end) {
    const offset = source.indexOf(start);
    assert.notEqual(offset, -1);
    const finish = source.indexOf(end, offset);
    assert.notEqual(finish, -1);
    return source.slice(offset, finish);
}

function dropFixture(order) {
    const rows = [];
    const mapped = new Map(order.map((code) => [code, true]));
    let saved;
    function slot(side, code) {
        return {
            side, code,
            getAttribute(name) { return name === 'data-side' ? this.side : name === 'data-code' ? this.code : ''; },
            classList: {remove() {}},
            closest() { return this.row; },
        };
    }
    for (const code of order) {
        const row = {
            left: slot('ergo', ''), right: slot('magento', code),
            querySelector(selector) { return selector.includes('ergo') ? this.left : this.right; },
            remove() { rows.splice(rows.indexOf(this), 1); },
        };
        row.left.row = row;
        row.right.row = row;
        rows.push(row);
    }
    const context = {
        root: {querySelectorAll: () => rows},
        draggedCard: {getAttribute: () => 'magento'},
        cardPayload: () => ({source: 'magento', code: '1'}),
        setStoreMapped: (code, active) => mapped.set(code, active),
        setSlotFilled: (target, payload) => { target.code = payload.code; },
        updateState() {}, dirty: {}, requirements: {},
        autosave: {schedule: () => { saved = rows.map((row) => row.right.code); }},
        scope: {delegate: (event, selector, handler) => { context.drop = handler; }},
    };
    // Run the actual handler and draft lookup. Only DOM/presentation and scheduling are fixtures.
    vm.runInNewContext(
        section('    function oppositeSource(', '    function findComplementaryDraft(') +
        section('    function slotPayload(', '    function setSlotFilled(') +
        section("                scope.delegate('drop', '[data-role=\"pair-slot\"]'",
            "                scope.delegate('dragover', '[data-role=\"mapping-panel\"]'"),
        context
    );
    return {
        drop(targetCode) {
            const target = rows.find((row) => row.right.code === targetCode).right;
            context.drop({preventDefault() {}, stopPropagation() {}}, target);
            return {saved, mapped};
        },
    };
}

for (const order of [['2', '1'], ['1', '2']]) {
    test(`moving Store View 1 replaces Store View 2 once, with DOM order ${order}`, () => {
        const {saved, mapped} = dropFixture(order).drop('2');
        assert.deepEqual(saved, ['1']);
        assert.equal(mapped.get('1'), true);
        assert.equal(mapped.get('2'), false);
    });
}
test('dropping a Store View on its own draft keeps that draft and the other one', () => {
    const {saved, mapped} = dropFixture(['2', '1']).drop('1');
    assert.deepEqual(saved, ['2', '1']);
    assert.equal(mapped.get('1'), true);
    assert.equal(mapped.get('2'), true);
});
