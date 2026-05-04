(function() {
    if (typeof window.vendor === 'undefined' || !window.vendor.wp || !window.vendor.wp.hooks) {
        return;
    }

    var __ = window.wp && window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function(text) {
        return text;
    };

    var moduleNames = [
        'divi/shop',
        'divi/woocommerce-related-products',
        'divi.shop',
        'divi.woocommerce-related-products'
    ];

    function normalizeModuleName(name) {
        return String(name || '').replace(/_/g, '-');
    }

    function isWooProductsModule(metadata) {
        var name = metadata && (metadata.name || metadata.moduleName || (metadata.module && metadata.module.name)) ? metadata.name || metadata.moduleName || metadata.module.name : '';
        var title = metadata && (metadata.title || metadata.moduleTitle) ? metadata.title || metadata.moduleTitle : '';
        var normalizedName = normalizeModuleName(name);

        return moduleNames.indexOf(normalizedName) !== -1
            || /^Woo Products$/i.test(title)
            || /^Woo Related Products$/i.test(title);
    }

    function getGroupSlug(metadata) {
        var groups = metadata && metadata.settings && metadata.settings.groups ? metadata.settings.groups : {};
        var preferred = ['contentMainContent', 'contentContent', 'content', 'mainContent', 'main_content'];
        var keys = Object.keys(groups);
        var i;

        for (i = 0; i < preferred.length; i++) {
            if (groups[preferred[i]]) {
                return preferred[i];
            }
        }

        for (i = 0; i < keys.length; i++) {
            if (keys[i].indexOf('content') !== -1 || groups[keys[i]].panel === 'content' || groups[keys[i]].panelName === 'content') {
                return keys[i];
            }
        }

        return 'content';
    }

    function getFieldConfig(metadata) {
        return {
            attrName: 'content.advanced.bapfApply',
            label: __('Apply BeRocket AJAX Filters', 'BeRocket_AJAX_domain'),
            description: __('All Filters will be applied to this module. You need correct unique selectors to work correct', 'BeRocket_AJAX_domain'),
            groupSlug: getGroupSlug(metadata),
            priority: 99,
            render: true,
            defaultAttr: {
                desktop: {
                    value: 'default'
                }
            },
            features: {
                hover: false,
                sticky: false,
                responsive: false,
                preset: 'content'
            },
            component: {
                type: 'field',
                name: 'divi/select',
                props: {
                    defaultValue: 'default',
                    options: {
                        default: {
                            label: __('Default', 'BeRocket_AJAX_domain')
                        },
                        enable: {
                            label: __('Enable', 'BeRocket_AJAX_domain')
                        },
                        disable: {
                            label: __('Disable', 'BeRocket_AJAX_domain')
                        }
                    }
                }
            }
        };
    }

    function addFieldToContentAdvancedSettings(attributes, metadata) {
        if (!attributes.content) {
            attributes.content = {
                type: 'object',
                settings: {}
            };
        }

        if (!attributes.content.settings) {
            attributes.content.settings = {};
        }

        if (!attributes.content.settings.advanced) {
            attributes.content.settings.advanced = {};
        }

        attributes.content.settings.advanced.bapfApply = {
            groupType: 'group-item',
            item: getFieldConfig(metadata)
        };
    }

    function addField(attributes, metadata) {
        if (!isWooProductsModule(metadata)) {
            return attributes;
        }

        addFieldToContentAdvancedSettings(attributes, metadata);

        return attributes;
    }

    function registerModuleAttributesFilter(hookName, namespace, metadataFallback) {
        window.vendor.wp.hooks.addFilter(
            hookName,
            namespace,
            function(attributes, metadata) {
                return addField(attributes, metadata || metadataFallback || {});
            },
            20
        );
    }

    registerModuleAttributesFilter(
        'divi.moduleLibrary.moduleAttributes',
        'berocket-aapf/divi-5-woo-products-field'
    );

    var registeredHookNames = {};

    moduleNames.forEach(function(moduleName) {
        var hookName = 'divi.moduleLibrary.moduleAttributes.' + moduleName.replace('/', '.');

        if (registeredHookNames[hookName]) {
            return;
        }

        registeredHookNames[hookName] = true;

        registerModuleAttributesFilter(
            hookName,
            'berocket-aapf/divi-5-woo-products-field-' + moduleName.replace(/[/.]/g, '-'),
            {
                name: moduleName,
                settings: {
                    groups: {
                        contentMainContent: {},
                        contentContent: {}
                    }
                }
            }
        );
    });
})();
