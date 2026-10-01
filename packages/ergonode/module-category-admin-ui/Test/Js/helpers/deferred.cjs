// Controllable jQuery-style transport for controller tests; callbacks run synchronously.
module.exports = function deferred() {
    let state = 'pending';
    let args;
    const callbacks = {resolved: [], rejected: []};
    const result = {
        done(fn) { subscribe('resolved', fn); return result; },
        fail(fn) { subscribe('rejected', fn); return result; },
        always(fn) { result.done(fn).fail(fn); return result; },
        promise() { return result; },
        resolve(...values) { settle('resolved', values); return result; },
        reject(...values) { settle('rejected', values); return result; },
        then(yes, no) {
            const next = deferred();
            function complete(fn, success, values) {
                if (!fn) { next[success ? 'resolve' : 'reject'](...values); return; }
                try {
                    const value = fn(...values);
                    if (value && typeof value.then === 'function') {
                        value.then(next.resolve, next.reject);
                    } else {
                        next.resolve(value);
                    }
                } catch (error) { next.reject(error); }
            }
            result.done((...values) => complete(yes, true, values));
            result.fail((...values) => complete(no, false, values));
            return next.promise();
        }
    };
    function subscribe(target, fn) {
        if (state === target) fn(...args);
        else if (state === 'pending') callbacks[target].push(fn);
    }
    function settle(target, values) {
        if (state !== 'pending') return;
        state = target;
        args = values;
        callbacks[target].forEach(fn => fn(...values));
    }
    return result;
};
