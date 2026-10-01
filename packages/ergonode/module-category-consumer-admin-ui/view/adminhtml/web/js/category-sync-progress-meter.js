define([], function () {
    'use strict';

    function phase(stage) {
        if (['applying_tree', 'creating_category', 'moving_category'].indexOf(stage) !== -1) {
            return 'tree';
        }
        if (stage === 'fetching_category_data') {
            return 'fetch';
        }
        if (stage === 'updating_category_data') {
            return 'data';
        }
        return '';
    }

    return function createProgressMeter() {
        var key = '', samples = [], last = null, advancedAt = 0;

        function reset() {
            key = '';
            samples = [];
            last = null;
            advancedAt = 0;
        }

        function update(progress, now) {
            var group = phase(progress.stage);
            var total = Number(progress.total);
            var processed = Number(progress.processed);
            var nextKey = [group, group === 'tree' ? progress.tree_code || '' : '', total].join(':');

            if (!group || !Number.isFinite(total) || total <= 0 || !Number.isFinite(processed)
                || processed < 0 || processed > total) {
                reset();
                return get(now);
            }
            if (key !== nextKey || (last && processed < last.processed)) {
                reset();
                key = nextKey;
            }
            if (!last || processed > last.processed) {
                samples.push({processed: processed, at: now});
                advancedAt = now;
                // Keep a recent, bounded sample window; repeated polling is not progress.
                while (samples.length > 2 && (samples.length > 30 || samples[1].at < now - 30000)) {
                    samples.shift();
                }
            }
            last = {processed: processed, total: total};
            return get(now);
        }

        function get(now) {
            var first = samples[0];
            var recent = samples[samples.length - 1];
            var stale = !!last && now - advancedAt >= 30000;
            var seconds = first && recent ? (recent.at - first.at) / 1000 : 0;
            var rate = !stale && seconds >= 5 ? (recent.processed - first.processed) / seconds : 0;

            return {
                known: !!last,
                processed: last ? last.processed : 0,
                total: last ? last.total : 0,
                percent: last ? Math.floor(last.processed / last.total * 100) : null,
                rate: rate,
                remaining: rate > 0 && last.processed < last.total
                    ? Math.ceil((last.total - last.processed) / rate) : null,
                stale: stale
            };
        }

        return {update: update, get: get, reset: reset};
    };
});
