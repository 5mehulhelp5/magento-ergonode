define(['mage/translate'], function ($t) {
    'use strict';

    function setBusy(button, busy, config) {
        config = config || {};

        if (!button) {
            return;
        }

        button.classList.toggle(config.busyClass || 'is-busy', busy);
        button.disabled = busy || Boolean(!busy && config.disabledWhenIdle && config.disabledWhenIdle());

        if (busy) {
            button.setAttribute('aria-busy', 'true');
        } else {
            button.removeAttribute('aria-busy');
        }
    }

    function run(button, task, config) {
        config = config || {};

        if (!button || button.disabled) {
            return Promise.reject(new Error($t('Akcja jest niedostępna.')));
        }

        setBusy(button, true, config);

        return Promise.resolve().then(task).then(function (result) {
            setBusy(button, false, config);

            return result;
        }).catch(function (error) {
            setBusy(button, false, config);
            throw error;
        });
    }

    function isPressed(button) {
        return Boolean(button && button.getAttribute('aria-pressed') === 'true');
    }

    function setPressed(button, pressed) {
        if (!button) {
            return false;
        }

        button.setAttribute('aria-pressed', pressed ? 'true' : 'false');

        return pressed;
    }

    function togglePressed(button) {
        return setPressed(button, !isPressed(button));
    }

    return {
        setBusy: setBusy,
        run: run,
        isPressed: isPressed,
        setPressed: setPressed,
        togglePressed: togglePressed
    };
});
