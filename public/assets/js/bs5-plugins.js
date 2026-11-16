/*!
 * bs5-plugins.js — keeps the Bootstrap 4-era plugins bundled in sximo.min.js working on Bootstrap 5.
 * The app's own markup is BS5; this is for what those plugins generate or expect at runtime:
 *  - Summernote renders data-toggle="dropdown|button" and data-dismiss="modal" -> mirrored to data-bs-*
 *  - sximo5.js / sximo.datatables.js read data-original-title -> mirrored back from data-bs-original-title
 *  - select2 & date pickers render on <body> -> exempt from the BS5 modal focus trap
 *  - one instance per element in BS5 -> no tooltip on dropdown/tab toggles (Summernote toolbar)
 *  - $.fn.modal/tooltip/... registered right away, as BS4 did
 * Load before bootstrap.bundle.min.js and call bs4CompatBridgeJquery() right after it.
 * Can go once those plugins are replaced by BS5-native versions.
 */
(function () {
    'use strict';

    var ATTRS = [
        'toggle', 'target', 'dismiss', 'parent', 'ride', 'slide', 'slide-to', 'interval',
        'backdrop', 'keyboard', 'focus', 'placement', 'trigger', 'content', 'html',
        'container', 'offset', 'delay', 'animation', 'boundary', 'reference', 'display',
        'spy', 'original-title', 'custom-class', 'fallback-placement', 'selector', 'pause', 'wrap'
    ];
    var SELECTOR = ATTRS.map(function (a) { return '[data-' + a + ']'; }).join(',') + ',.dropdown-menu-right';

    function upgrade(el) {
        for (var i = 0; i < ATTRS.length; i++) {
            var name = 'data-' + ATTRS[i];
            if (el.hasAttribute(name) && !el.hasAttribute('data-bs-' + ATTRS[i])) {
                el.setAttribute('data-bs-' + ATTRS[i], el.getAttribute(name));
            }
        }
        if (el.classList && el.classList.contains('dropdown-menu-right')) {
            el.classList.add('dropdown-menu-end');
        }
    }

    function scan(root) {
        if (root.nodeType !== 1) return;
        if (root.matches(SELECTOR)) upgrade(root);
        var found = root.querySelectorAll(SELECTOR);
        for (var i = 0; i < found.length; i++) upgrade(found[i]);
    }

    new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var m = mutations[i];
            if (m.type === 'attributes') {
                // Re-sync when old-style attributes are changed later, e.g. $(el).attr('data-target', ...).
                // data-bs-original-title goes the other way: BS5 tooltips move title there, while
                // sximo5.js / sximo.datatables.js read data-original-title for the modal title.
                var from = m.attributeName;
                var to = from === 'data-bs-original-title' ? 'data-original-title' : from.replace(/^data-/, 'data-bs-');
                var value = m.target.getAttribute(from);
                if (value !== null && m.target.getAttribute(to) !== value) {
                    m.target.setAttribute(to, value);
                }
            } else {
                for (var j = 0; j < m.addedNodes.length; j++) scan(m.addedNodes[j]);
            }
        }
    }).observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ATTRS.map(function (a) { return 'data-' + a; }).concat('data-bs-original-title')
    });

    scan(document.documentElement);

    // BS5's modal focus trap pulls focus back into the modal whenever something outside it is focused.
    // Plugins that render popups on <body> (select2 search box, date pickers, SweetAlert...) became
    // untypeable inside modals; BS4 left them alone. Stop the focusin before it reaches the trap.
    var BODY_POPUPS = '.select2-container,.dtp,.ui-datepicker,.note-popover,.note-modal,.swal2-container';
    window.addEventListener('focusin', function (e) {
        var t = e.target;
        if (t && t.closest && t.closest(BODY_POPUPS) && document.querySelector('.modal.show')) {
            e.stopPropagation();
        }
    }, true);

    // BS5 attaches $.fn.modal/tooltip/... only on DOMContentLoaded; BS4 did it as soon as the script
    // loaded. Until then $.fn.tooltip would be jQuery UI's. Call right after bootstrap.bundle.js.
    window.bs4CompatBridgeJquery = function () {
        var $ = window.jQuery;
        if (!$ || !window.bootstrap) return;
        ['Alert', 'Button', 'Carousel', 'Collapse', 'Dropdown', 'Modal', 'Offcanvas', 'Popover', 'ScrollSpy', 'Tab', 'Toast', 'Tooltip']
            .forEach(function (name) {
                var plugin = window.bootstrap[name];
                if (!plugin || $.fn[plugin.NAME] === plugin.jQueryInterface) return;
                var previous = $.fn[plugin.NAME];
                $.fn[plugin.NAME] = plugin.jQueryInterface;
                $.fn[plugin.NAME].Constructor = plugin;
                $.fn[plugin.NAME].noConflict = function () {
                    $.fn[plugin.NAME] = previous;
                    return plugin.jQueryInterface;
                };
            });
        guardTooltip($);
        // BS5 re-assigns $.fn.* on DOMContentLoaded; its listener was added first, so re-guard after it
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { guardTooltip($); });
        }
    };

    // BS5 allows one component instance per element (BS4 allowed several). A tooltip on a dropdown/tab
    // toggle — e.g. every Summernote toolbar dropdown — blocks the Dropdown instance, so the menu never
    // closes on outside click. Skip the tooltip there; the title attribute stays as a native tooltip.
    var INSTANCE_TOGGLES = '[data-toggle="dropdown"],[data-bs-toggle="dropdown"],[data-toggle="tab"],[data-bs-toggle="tab"],[data-toggle="pill"],[data-bs-toggle="pill"]';
    function guardTooltip($) {
        var iface = window.bootstrap.Tooltip.jQueryInterface;
        if ($.fn.tooltip && $.fn.tooltip.bs4CompatGuarded) return;
        var guarded = function () {
            iface.apply(this.not(INSTANCE_TOGGLES), arguments);
            return this;
        };
        guarded.Constructor = window.bootstrap.Tooltip;
        guarded.noConflict = $.fn.tooltip && $.fn.tooltip.noConflict;
        guarded.bs4CompatGuarded = true;
        $.fn.tooltip = guarded;
    }
})();
