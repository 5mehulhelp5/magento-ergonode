const assert = require('node:assert/strict');
const test = require('node:test');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
function fixture(publish) {
    let factory, controls;
    const events = [];
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/publication-process.js'), 'utf8'), {
        define: (_, build) => { factory = build(text => text); }, Promise, Set, Error
    });
    const progress = Object.fromEntries(['setPauseState','showBatch','applyBatch','stopped','complete','fail'].map(name =>
        [name, (...args) => events.push([name, ...args])]
    ));
    progress.open = (_, value) => { controls = value; };
    return { process: factory(progress, publish, () => events.push(['finish'])), events, controls: () => controls };
}
function response(ids) { return {success:true, items:ids.map(product_id => ({product_id, status:'success'}))}; }
const tick = () => new Promise(resolve => setImmediate(resolve));
test('pause finishes current batch; resume sends only remaining IDs', async () => {
    const batches = []; let release;
    const f = fixture(ids => { batches.push([...ids]); return new Promise(resolve => { release = () => resolve(response(ids)); }); });
    const run = f.process.start(Array.from({length:51}, (_,i) => i+1), id => ({code:id}));
    await tick(); f.controls().pause(); release(); await tick();
    assert.equal(batches.length,1);
    assert.equal(f.events.some(e => e[0] === 'setPauseState' && e[1] === 'paused'),true);
    f.controls().resume(); await tick(); assert.deepEqual(batches[1],[51]); release(); await run;
    assert.equal(f.events.some(e => e[0] === 'complete'),true);
});
test('stop during a request waits for its response and never sends next batch', async () => {
    let release; const batches=[];
    const f = fixture(ids => { batches.push(ids); return new Promise(resolve => { release = () => resolve(response(ids)); }); });
    const run=f.process.start(Array.from({length:51},(_,i)=>i+1), id=>({code:id}));
    await tick(); f.controls().stop(); assert.equal(f.process.isRunning(),true); release(); await run;
    assert.equal(batches.length,1); assert.equal(f.events.some(e=>e[0]==='stopped'),true);
});
test('stop while paused releases the process without another request', async () => {
    let release;
    const f=fixture(ids=>new Promise(resolve=>{release=()=>resolve(response(ids));}));
    const run=f.process.start(Array.from({length:51},(_,i)=>i+1),id=>({code:id}));
    await tick(); f.controls().pause(); release(); await tick(); f.controls().stop(); await run;
    assert.equal(f.process.isRunning(),false); assert.equal(f.events.some(e=>e[0]==='stopped'),true);
});
test('ambiguous transport failure is never automatically replayed', async () => {
    let calls=0;
    const f=fixture(()=>{calls++; return Promise.reject(new Error('Disconnected'));});
    await f.process.start([1],id=>({code:id}));
    assert.equal(calls,1); assert.match(f.events.find(e=>e[0]==='fail')[1],/partially completed/);
});
test('an incomplete batch response cannot advance progress', async () => {
    const f=fixture(()=>Promise.resolve(response([1])));
    await f.process.start([1,2],id=>({code:id}));
    assert.equal(f.events.some(e=>e[0]==='applyBatch'),false);
    assert.equal(f.events.some(e=>e[0]==='fail'),true);
});

test('popup receives correlated results even when the response order differs', async () => {
    const f=fixture(()=>Promise.resolve(response([2,1])));
    await f.process.start([1,2],id=>({product_id:id,code:String(id)}));
    assert.deepEqual(Array.from(f.events.find(e=>e[0]==='showBatch')[3],item=>item.product_id),[1,2]);
    assert.deepEqual(Array.from(f.events.find(e=>e[0]==='applyBatch')[1],item=>item.product_id),[2,1]);
});

test('stop during the final batch reports stopped after recording its result', async () => {
    let release;
    const f = fixture(ids => new Promise(resolve => { release = () => resolve(response(ids)); }));
    const run = f.process.start([1], id => ({code:id}));
    await tick(); f.controls().stop(); release(); await run;
    assert.deepEqual(f.events.map(event => event[0]), ['showBatch', 'applyBatch', 'stopped', 'finish']);
});

test('product workspace keeps table scrolling inside the bounded panel', () => {
    const styles = fs.readFileSync(
        path.join(__dirname, '../../view/adminhtml/web/css/publication.css'),
        'utf8',
    );

    assert.match(styles, /\.vepp-workspace\s*\{[\s\S]*?display:\s*flex;[\s\S]*?flex-direction:\s*column;/);
    assert.match(styles, /\.vepp-workspace\s*>\s*\.veui-toolbar\s*\{[\s\S]*?flex:\s*0 0 68px;/);
    assert.match(styles, /\.vepp-panel\s*\{[\s\S]*?flex:\s*1 1 auto;[\s\S]*?min-height:\s*0;/);
    assert.match(styles, /\.vepp-table-region\s*\{[\s\S]*?flex:\s*1 1 auto;[\s\S]*?min-height:\s*0;[\s\S]*?overflow:\s*hidden;/);
    assert.match(styles, /\.vepp-table-scroll\s*\{[\s\S]*?height:\s*100%;/);
});
