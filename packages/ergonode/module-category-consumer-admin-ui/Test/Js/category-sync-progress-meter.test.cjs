'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
let createMeter;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/category-sync-progress-meter.js'), 'utf8'), {
    define: (_dependencies, factory) => { createMeter = factory(); }
});

const point = (processed, extra = {}) => ({stage: 'creating_category', tree_code: 'tghome_1', total: 100, processed, ...extra});

test('percentage uses the server count; ETA needs elapsed time and forward progress', () => {
    const meter = createMeter();
    let value = meter.update(point(10), 0);
    assert.equal(value.percent, 10);
    assert.equal(value.remaining, null);
    value = meter.update(point(11), 1000);
    assert.equal(value.remaining, null);
    value = meter.update(point(20, {stage: 'moving_category'}), 5000);
    assert.equal(value.rate, 2);
    assert.equal(value.remaining, 40);
    value = meter.update(point(100), 10000);
    assert.equal(value.percent, 100);
    assert.equal(value.remaining, null);
});

test('download, zero, missing and invalid totals never become a fabricated percentage', () => {
    const meter = createMeter();
    for (const extra of [{stage: 'fetching_tree'}, {total: null}, {total: 0}, {total: undefined},
        {total: 'invalid'}, {processed: -1}, {processed: 101}, {stage: 'deleting_categories'}]) {
        meter.update(point(5), 0);
        const value = meter.update(point(10, extra), 5000);
        assert.equal(value.known, false);
        assert.equal(value.percent, null);
        assert.equal(value.remaining, null);
    }
});

test('tree, denominator, counter rollback and data stage changes reset the estimate', () => {
    for (const extra of [{tree_code: 'other'}, {total: 200}, {processed: 5}, {stage: 'fetching_category_data'}]) {
        const meter = createMeter();
        meter.update(point(10), 0);
        assert.equal(meter.update(point(20), 5000).remaining, 40);
        assert.equal(meter.update(point(21, extra), 6000).remaining, null);
    }
    const meter = createMeter();
    meter.update(point(10, {stage: 'fetching_category_data'}), 0);
    assert.equal(meter.update(point(20, {stage: 'fetching_category_data'}), 5000).remaining, 40);
    assert.equal(meter.update(point(20, {stage: 'updating_category_data'}), 6000).remaining, null);
});

test('repeated polls do not add progress or keep a stale ETA alive', () => {
    const meter = createMeter();
    meter.update(point(10), 0);
    meter.update(point(20), 5000);
    for (let now = 6000; now <= 34000; now += 1000) {
        assert.equal(meter.update(point(20), now).remaining, 40);
    }
    const stale = meter.get(35000);
    assert.equal(stale.stale, true);
    assert.equal(stale.remaining, null);
    assert.equal(stale.percent, 20);
});

test('reset on resume excludes the paused interval from new rate samples', () => {
    const meter = createMeter();
    meter.update(point(10), 0);
    meter.update(point(20), 5000);
    meter.reset();
    assert.equal(meter.update(point(20), 600000).remaining, null);
    assert.equal(meter.update(point(30), 605000).rate, 2);
});

test('recent samples replace the initial rate without unbounded storage', () => {
    const meter = createMeter();
    meter.update(point(0, {total: 1000}), 0);
    for (let second = 1; second <= 100; second++) {
        meter.update(point(second <= 50 ? second : 50 + (second - 50) * 2, {total: 1000}), second * 1000);
    }
    assert.equal(meter.get(100000).rate, 2);
});
