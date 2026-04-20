/* global mdtFrontend, jQuery */
(function ($) {
    'use strict';

    var lang       = mdtFrontend.activeLang  || '';
    var targets    = mdtFrontend.targets      || [];
    var resetParam = mdtFrontend.resetParam   || 'mdt_reset';

    /**
     * Add or remove the ?lang= query param from a URL string.
     * Leaves non-internal, anchor-only, and javascript: hrefs unchanged.
     */
    function addLangToUrl( url, langCode ) {
        if ( !url || url.charAt(0) === '#' || url.indexOf('javascript') === 0 ) {
            return url;
        }

        // Remove any existing lang param and reset param first
        url = removeParam( url, 'lang' );
        url = removeParam( url, resetParam );

        if ( langCode ) {
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            url = url + sep + 'lang=' + encodeURIComponent(langCode);
        }
        return url;
    }

    function removeParam( url, param ) {
        return url
            .replace( new RegExp('[?&]' + param + '=[^&]*', 'g'), '' )
            .replace( /^([^?]*)(&)/, '$1?' ); // fix leading & if ? was removed
    }

    /**
     * Returns true if the href is an internal link (same origin).
     */
    function isInternal( href ) {
        if ( !href ) return false;
        if ( href.charAt(0) === '/' || href.charAt(0) === '.' ) return true;
        try {
            var a = document.createElement('a');
            a.href = href;
            return a.hostname === window.location.hostname;
        } catch(e) {
            return false;
        }
    }

    /**
     * Patch all internal links on the page to carry the current lang param.
     * Skips links that already have mdt_reset or a different lang switcher link.
     */
    function patchLinks() {
        if ( !lang ) return;

        $('a[href]').each(function () {
            var $a  = $(this);
            var href = $a.attr('href');

            // Don't modify switcher links — they manage themselves
            if ( $a.closest('.mdt-switcher').length ) return;

            if ( !isInternal(href) ) return;

            // Already has mdt_reset — leave alone
            if ( href.indexOf( resetParam ) !== -1 ) return;

            $a.attr( 'href', addLangToUrl( href, lang ) );
        });
    }

    $(function () {
        // Patch existing links on DOMReady
        patchLinks();

        // Patch links added dynamically (Elementor, AJAX menus, etc.)
        if ( typeof MutationObserver !== 'undefined' ) {
            var observer = new MutationObserver(function ( mutations ) {
                mutations.forEach(function ( m ) {
                    m.addedNodes.forEach(function ( node ) {
                        if ( node.nodeType === 1 ) { // Element node
                            $(node).find('a[href]').addBack('a[href]').each(function () {
                                var $a   = $(this);
                                var href = $a.attr('href');
                                if ( $a.closest('.mdt-switcher').length ) return;
                                if ( !isInternal(href) ) return;
                                if ( href.indexOf(resetParam) !== -1 ) return;
                                $a.attr('href', addLangToUrl(href, lang));
                            });
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }

        // Loading state when switching language
        $(document).on('click', '.mdt-switcher__link, .mdt-switcher__flag-item', function () {
            if ( !$(this).closest('.mdt-switcher__item--active, .mdt-switcher__flag-item--active').length ) {
                $('body').addClass('mdt-translating');
            }
        });

        // Dropdown change already navigates via onchange — show loading state
        $(document).on('change', '.mdt-switcher__select', function () {
            $('body').addClass('mdt-translating');
        });
    });

}(jQuery));
