const assert = require('node:assert/strict');
const test = require('node:test');
const {fixture, settle, initialRevision, nextRevision} = require('./support/autosave-fixture.cjs');

test('queued edits wait for the active save and use its returned revision', async () => {
    const view = fixture();
    const autosave = view.createAutosave();
    autosave.schedule();
    await view.time.advance(350);
    assert.equal(view.calls.length, 1);
    view.state.code = 'en_GB';
    autosave.schedule();
    await view.time.advance(350);
    view.state.code = 'de_DE';
    autosave.schedule();
    let flushed = false;
    const waiting = autosave.flush().then(() => { flushed = true; });
    assert.equal(view.calls.length, 1);
    assert.equal(view.calls[0].payload.mappings[0].left.code, 'pl_PL');

    view.calls[0].respond({success: true, revision: nextRevision});
    await settle();
    assert.equal(view.calls.length, 2);
    assert.equal(view.calls[1].payload.revision, nextRevision);
    assert.equal(view.calls[1].payload.mappings[0].left.code, 'de_DE');
    assert.equal(flushed, false);
    assert.equal(view.notifications.length, 0);
    assert.equal(view.region.getAttribute('data-autosave-state'), 'saving');
    assert.equal(view.region.getAttribute('inert'), '');
    assert.equal(view.loader.hidden, false);

    view.calls[1].respond({success: true, revision: 'c'.repeat(64)});
    await waiting;
    assert.equal(view.config.revision, 'c'.repeat(64));
    assert.equal(view.notifications.length, 1);
    assert.equal(view.notifications[0].snapshot.mappings[0].left.code, 'de_DE');
    assert.equal(view.region.getAttribute('data-autosave-state'), 'saved');
    assert.equal(view.root.getAttribute('data-language-busy'), 'false');
    assert.equal(view.loader.hidden, true);
    await view.time.advance(1000);
    assert.equal(view.calls.length, 2);
});

test('flush starts a debounced edit immediately and all callers wait for its response', async () => {
    const view = fixture();
    const autosave = view.createAutosave();
    autosave.schedule();
    const completed = [];
    const first = autosave.flush().then(() => completed.push('first'));
    const second = autosave.flush().then(() => completed.push('second'));
    await settle();
    assert.equal(view.calls.length, 1);
    assert.deepEqual(completed, []);
    view.calls[0].respond({success: true, revision: nextRevision});
    await Promise.all([first, second]);
    assert.deepEqual(completed, ['first', 'second']);
    await view.time.advance(350);
    assert.equal(view.calls.length, 1);
});

test('failed in-flight save blocks queued edits until retry sends the latest snapshot', async () => {
    const view = fixture();
    const autosave = view.createAutosave();
    autosave.schedule();
    await view.time.advance(350);
    view.state.code = 'de_DE';
    autosave.schedule();
    const failedFlush = assert.rejects(autosave.flush(), /Connection lost/);
    view.calls[0].reject(new Error('Connection lost'));
    await failedFlush;
    await view.time.advance(5000);
    assert.equal(view.calls.length, 1);
    assert.equal(view.config.revision, initialRevision);
    assert.equal(view.error.hidden, false);
    assert.equal(view.root.getAttribute('data-language-busy'), 'false');
    assert.equal(view.notifications.length, 0);
    await assert.rejects(autosave.flush(), /Connection lost/);

    autosave.retry();
    autosave.retry();
    await settle();
    assert.equal(view.calls.length, 2);
    assert.equal(view.calls[1].payload.revision, initialRevision);
    assert.equal(view.calls[1].payload.mappings[0].left.code, 'de_DE');
    const finished = autosave.flush();
    view.calls[1].respond({success: true, revision: nextRevision});
    await finished;
    assert.equal(view.error.hidden, true);
    assert.equal(view.config.revision, nextRevision);
    assert.equal(view.notifications.length, 1);
});

test('lost save response followed by HTTP 409 keeps the original revision and requires reload', async () => {
    const view = fixture();
    const autosave = view.createAutosave();
    autosave.schedule();
    const lost = assert.rejects(autosave.flush(), /response lost/);
    await settle();
    view.calls[0].reject(new Error('Save response lost'));
    await lost;
    autosave.retry();
    const conflict = assert.rejects(autosave.flush(), /Reload the page/);
    await settle();
    view.calls[1].respond({success: false, message: 'Reload the page.', revision: nextRevision}, 409);
    await conflict;
    await view.time.advance(5000);
    assert.equal(view.calls.length, 2);
    assert.equal(view.calls[1].payload.revision, initialRevision);
    assert.equal(view.config.revision, initialRevision);
    assert.equal(autosave.hasError(), true);
    assert.equal(view.region.getAttribute('data-autosave-state'), 'error');
    assert.equal(view.notifications.length, 0);
});

for (const [name, revision] of Object.entries({missing: undefined, numeric: 123, short: 'abc', uppercase: 'A'.repeat(64)})) {
    test(`invalid ${name} response revision does not acknowledge pending edits`, async () => {
        const view = fixture();
        const autosave = view.createAutosave();
        autosave.schedule();
        const failed = assert.rejects(autosave.flush(), /Missing or invalid language mapping revision/);
        await settle();
        view.calls[0].respond({success: true, revision});
        await failed;
        assert.equal(view.config.revision, initialRevision);
        assert.equal(view.notifications.length, 0);
        assert.equal(view.error.hidden, false);
        assert.equal(view.loader.hidden, true);
    });
}

test('serialization failure sends no request and an explicit retry can recover', async () => {
    const view = fixture();
    let fails = true;
    const autosave = view.createAutosave({serialize() {
        if (fails) throw new Error('Cannot serialize mappings');
        return {mappings: []};
    }});
    autosave.schedule();
    await assert.rejects(autosave.flush(), /Cannot serialize mappings/);
    assert.equal(view.calls.length, 0);
    assert.equal(view.config.revision, initialRevision);
    assert.equal(view.loader.hidden, true);
    fails = false;
    autosave.retry();
    await settle();
    const saved = autosave.flush();
    view.calls[0].respond({success: true, revision: nextRevision});
    await saved;
    assert.equal(autosave.hasError(), false);
});
