import React, { useEffect, useMemo, useState } from 'react';

const {
  ModuleContainer,
  StyleContainer,
  elementClassnames,
} = window?.divi?.module || {};

const ModuleStyles = ({
  elements,
  settings,
  mode,
  state,
  noStyleTag,
}) => (
  <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
    {elements.style({
      attrName: 'module',
      styleProps: {
        disabledOn: {
          disabledModuleVisibility: settings?.disabledModuleVisibility,
        },
      },
    })}
  </StyleContainer>
);

const ModuleScriptData = ({ elements }) => (
  <React.Fragment>
    {elements.scriptData({
      attrName: 'module',
    })}
  </React.Fragment>
);

const moduleClassnames = ({ classnamesInstance, attrs }) => {
  classnamesInstance.add(
    elementClassnames({
      attrs: attrs?.module?.decoration ?? {},
    }),
  );
};

const option = (label) => ({ label });

const getPreviewConfig = () => {
  if (window?.BAPFDivi5Preview) {
    return window.BAPFDivi5Preview;
  }

  const scriptSrc = document?.currentScript?.src;
  if (!scriptSrc) {
    return {};
  }

  const params = new URL(scriptSrc).searchParams;
  const jsonParam = (name) => {
    try {
      return JSON.parse(decodeURIComponent(params.get(name) || '{}'));
    } catch (error) {
      return {};
    }
  };

  return {
    ajaxUrl: decodeURIComponent(params.get('bapf_ajax_url') || ''),
    action: params.get('bapf_action') || '',
    nonce: params.get('bapf_nonce') || '',
    filters: jsonParam('bapf_filters'),
    groups: jsonParam('bapf_groups'),
  };
};

const previewConfig = getPreviewConfig();

const toSelectOptions = (items, fallbackLabel) => {
  const options = Object.entries(items ?? {}).reduce((result, [value, label]) => ({
    ...result,
    [value]: option(label),
  }), {});

  return Object.keys(options).length ? options : { 0: option(fallbackLabel) };
};

const toFilterSelectOptions = (items, fallbackLabel) => {
  const options = toSelectOptions(items, fallbackLabel);

  Object.entries(items ?? {}).forEach(([value, label]) => {
    const idMatch = String(label).match(/\(ID:(\d+)\)/);
    if (!idMatch) {
      return;
    }

    const title = String(label).replace(/\s*\(ID:\d+\)\s*$/, '');
    options[`(ID:${idMatch[1]}) ${title}`] = option(label);
    options[value] = option(label);
  });

  return options;
};

const selectOptions = {
  titleLevel: {
    '': option('Default'),
    h1: option('H1'),
    h2: option('H2'),
    h3: option('H3'),
    h4: option('H4'),
    h5: option('H5'),
    h6: option('H6'),
  },
  filters: toFilterSelectOptions(previewConfig.filters, '--Please create filter first--'),
  groups: toSelectOptions(previewConfig.groups, '--Please create group first--'),
};

const getGroupItemRegistry = () => {
  window.BAPFDivi5GroupItemFilters = window.BAPFDivi5GroupItemFilters || {};
  return window.BAPFDivi5GroupItemFilters;
};

const getRegisteredGroupItemFilters = () => Object.values(getGroupItemRegistry())
  .filter((filterId) => filterId !== undefined && filterId !== null && filterId !== '' && filterId !== '0' && filterId !== 0);

const fieldTypeMap = {
  title_level: { name: 'divi/select', props: { options: selectOptions.titleLevel } },
  filter_id: { name: 'divi/select', props: { options: selectOptions.filters } },
  group_id: { name: 'divi/select', props: { options: selectOptions.groups } },
};

const builtInGroupOnlyFields = new Set([
  'display_inline',
  'display_inline_count',
  'min_filter_width_inline',
  'hidden_clickable',
  'hidden_clickable_hover',
  'group_is_hide',
  'group_is_hide_theme',
  'group_is_hide_icon_theme',
  'title_level',
]);

const isBuiltInDiviGroup = (value) => value === undefined || value === null || value === '' || value === '0' || value === 0;

const getInnerContentValue = (attr) => {
  if (typeof attr?.asMutable === 'function') {
    attr = attr.asMutable({ deep: true });
  }

  if (attr?.innerContent?.desktop?.value !== undefined) {
    return attr.innerContent.desktop.value;
  }

  if (attr?.innerContent?.desktop !== undefined && typeof attr.innerContent.desktop !== 'object') {
    return attr.innerContent.desktop;
  }

  return undefined;
};

const getAttrValue = (attrs, attrName) => {
  if (!attrs) {
    return undefined;
  }

  if (typeof attrs?.asMutable === 'function') {
    attrs = attrs.asMutable({ deep: true });
  }

  const directValue = getInnerContentValue(attrs[attrName]);
  if (directValue !== undefined) {
    return directValue;
  }

  if (attrs[attrName] !== undefined && typeof attrs[attrName] !== 'object') {
    return attrs[attrName];
  }

  return undefined;
};

const extractFilterIdsFromChildren = (children) => {
  const filters = [];
  const collect = (value) => {
    if (!value) {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach(collect);
      return;
    }

    if (typeof value !== 'object') {
      return;
    }

    if (typeof value?.asMutable === 'function') {
      value = value.asMutable({ deep: true });
    }

    const attrs = value.props?.attrs ?? value.attrs ?? value.attributes;
    const filterId = getAttrValue(attrs, 'filter_id');

    if (filterId !== undefined && filterId !== null && filterId !== '') {
      filters.push(filterId);
    }

    collect(value.props?.content);
    collect(value.props?.children);
    collect(value.content);
    collect(value.children);
    collect(value.innerBlocks);
  };

  collect(children);

  return filters;
};

const callStoreSelector = (selector, method, ...args) => {
  if (!selector || typeof selector[method] !== 'function') {
    return undefined;
  }

  try {
    return selector[method](...args);
  } catch (error) {
    return undefined;
  }
};

const getDiviDataStores = () => {
  const stores = [
    window?.divi?.data,
    window?.wp?.data,
    window?.top?.divi?.data,
    window?.top?.wp?.data,
  ].filter(Boolean);

  return stores.filter((store, index) => stores.indexOf(store) === index);
};

const extractFilterIdsFromStore = (moduleId) => {
  if (!moduleId) {
    return [];
  }

  const filters = [];
  const collect = (value) => {
    const before = filters.length;
    extractFilterIdsFromChildren(value).forEach((filterId) => filters.push(filterId));

    if (before !== filters.length) {
      return;
    }

    if (!value || typeof value !== 'object') {
      return;
    }

    if (typeof value?.asMutable === 'function') {
      value = value.asMutable({ deep: true });
    }

    collect(value.children);
    collect(value.innerBlocks);
    collect(value.content);
    collect(value.props?.children);
    collect(value.props?.content);
  };

  getDiviDataStores().forEach((dataStore) => {
    if (!dataStore?.select) {
      return;
    }

    const selector = dataStore.select('divi/edit-post');

    collect(callStoreSelector(selector, 'getModuleWithChildren', moduleId));
    collect(callStoreSelector(selector, 'getModuleAttrs', moduleId));
    collect(callStoreSelector(selector, 'getModule', moduleId));

    [
      callStoreSelector(selector, 'getModuleStructureIds', moduleId),
      callStoreSelector(selector, 'getModuleStructureIds'),
    ].forEach((structureIds) => {
      if (!Array.isArray(structureIds)) {
        return;
      }

      structureIds.flat(Infinity).forEach((childId) => {
        if (!childId || childId === moduleId) {
          return;
        }

        const childName = callStoreSelector(selector, 'getModuleName', childId);
        if (childName && childName !== 'bapf/filters-group-item') {
          return;
        }

        collect(callStoreSelector(selector, 'getModuleAttrs', childId));
        collect(callStoreSelector(selector, 'getModule', childId));
      });

      const modules = callStoreSelector(selector, 'getModulesByIds', structureIds.flat(Infinity));
      collect(modules);
    });
  });

  return Array.from(new Set(filters.filter((filterId) => filterId !== '0' && filterId !== 0)));
};

const getDiviModuleAttrs = (moduleId) => {
  for (const dataStore of getDiviDataStores()) {
    if (!dataStore?.select) {
      continue;
    }

    const attrs = callStoreSelector(dataStore.select('divi/edit-post'), 'getModuleAttrs', moduleId);
    if (attrs) {
      return attrs;
    }
  }

  return undefined;
};

const subscribeDiviData = (callback) => {
  const unsubscribers = getDiviDataStores()
    .map((dataStore) => {
      if (!dataStore?.subscribe) {
        return null;
      }

      return dataStore.subscribe(callback);
    })
    .filter(Boolean);

  return () => unsubscribers.forEach((unsubscribe) => unsubscribe());
};

const isBuiltInDiviGroupVisible = (context = {}) => {
  let groupId = getAttrValue(context.attrs, 'group_id');

  if (groupId === undefined && context.moduleId) {
    groupId = getAttrValue(getDiviModuleAttrs(context.moduleId), 'group_id');
  }

  return isBuiltInDiviGroup(groupId);
};

const closestByText = (node, selectors, textPattern) => {
  let current = node;

  while (current && current !== document.body) {
    if (current.matches?.(selectors) && textPattern.test(current.textContent || '')) {
      return current;
    }

    current = current.parentElement;
  }

  return null;
};

const findGroupIdSettingRoot = (settingsRoot) => {
  const candidates = settingsRoot.querySelectorAll([
    '[data-name*="group_id"]',
    '[data-attr-name*="group_id"]',
    '[data-field-name*="group_id"]',
    '[name*="group_id"]',
    'label',
    '.et-vb-settings-option',
    '.et-fb-settings-option',
  ].join(','));

  for (const candidate of candidates) {
    if (/group\s*id/i.test(candidate.textContent || '') || /group_id/i.test(candidate.getAttribute('name') || '')) {
      const root = closestByText(
        candidate,
        '.et-vb-settings-option, .et-fb-settings-option, .et-vb-option, .et-fb-option, [class*="settings-option"], [class*="field"]',
        /group\s*id/i,
      );

      if (root) {
        return root;
      }
    }
  }

  return null;
};

const readGroupIdFromSettings = (groupIdRoot) => {
  if (!groupIdRoot) {
    return undefined;
  }

  const formField = groupIdRoot.querySelector('select, input, textarea');
  if (formField?.value !== undefined) {
    return formField.value;
  }

  const selected = groupIdRoot.querySelector([
    '[aria-selected="true"]',
    '.et-vb-selected-item',
    '.et-fb-selected-item',
    '[class*="selected"]',
  ].join(','));

  const selectedText = selected?.textContent?.trim();
  if (!selectedText || /build\s*in\s*divi/i.test(selectedText)) {
    return '0';
  }

  const idMatch = selectedText.match(/\b(\d+)\b/);
  if (idMatch) {
    return idMatch[1];
  }

  return selectedText;
};

const setHiddenByElement = (element, isHidden) => {
  if (!element) {
    return;
  }

  element.classList.toggle('bapf-divi5-hidden-child-control', isHidden);
  if (isHidden) {
    element.setAttribute('aria-hidden', 'true');
  } else {
    element.removeAttribute('aria-hidden');
  }
};

const applyGroupChildVisibility = () => {
  const settingsRoots = document.querySelectorAll([
    '.et-vb-modal',
    '.et-fb-modal',
    '.et-vb-settings',
    '.et-fb-settings',
    '[class*="settings-modal"]',
  ].join(','));

  settingsRoots.forEach((settingsRoot) => {
    if (!/group\s*filter/i.test(settingsRoot.textContent || '')) {
      return;
    }

    const groupIdRoot = findGroupIdSettingRoot(settingsRoot);
    const shouldHideChildren = !isBuiltInDiviGroup(readGroupIdFromSettings(groupIdRoot));
    const childControls = settingsRoot.querySelectorAll([
      '[data-module-name="bapf/filters-group-item"]',
      '[data-module_type="bapf/filters-group-item"]',
      '[data-module-type="bapf/filters-group-item"]',
      '[data-name="bapf/filters-group-item"]',
      '.et_pb_br_filters_group_item',
      '[class*="child"]',
      '[class*="sortable"]',
      '[class*="module-item"]',
      'button',
      'a',
    ].join(','));

    childControls.forEach((control) => {
      const text = control.textContent || '';
      const aria = control.getAttribute('aria-label') || '';
      const title = control.getAttribute('title') || '';
      const marker = `${text} ${aria} ${title}`;

      if (/bapf\/filters-group-item|et_pb_br_filters_group_item|(^|\s)filter(\s|$)|add\s+filter/i.test(marker)) {
        setHiddenByElement(
          closestByText(control, '.et-vb-settings-option, .et-fb-settings-option, [class*="sortable"], [class*="module-item"], li, button, a', /filter|add/i) || control,
          shouldHideChildren,
        );
      }
    });
  });
};

const ensureGroupChildVisibilityRuntime = () => {
  if (window.BAPFDivi5GroupChildVisibilityRuntime) {
    return;
  }

  window.BAPFDivi5GroupChildVisibilityRuntime = true;

  const style = document.createElement('style');
  style.id = 'bapf-divi5-group-child-visibility';
  style.textContent = '.bapf-divi5-hidden-child-control{display:none!important;}';
  document.head.appendChild(style);

  const scheduleApply = () => {
    window.requestAnimationFrame(applyGroupChildVisibility);
  };

  document.addEventListener('change', scheduleApply, true);
  document.addEventListener('click', () => window.setTimeout(applyGroupChildVisibility, 50), true);

  const observer = new MutationObserver(scheduleApply);
  observer.observe(document.body, {
    attributes: true,
    childList: true,
    subtree: true,
  });

  scheduleApply();
};

const normalizeMetadataFields = (metadata) => {
  metadata.settings = {
    ...(metadata.settings ?? {}),
    groups: {
      ...(metadata.settings?.groups ?? {}),
      contentOptions: {
        panel: 'content',
        priority: 10,
        groupName: 'contentOptions',
        multiElements: true,
        component: {
          name: 'divi/composite',
          props: {
            groupLabel: 'Content',
          },
        },
      },
    },
  };

  Object.entries(metadata?.attributes ?? {}).forEach(([attrName, attr]) => {
    const item = attr?.settings?.innerContent?.item;
    const fieldDefinition = fieldTypeMap[attrName];

    if (!item?.component) {
      return;
    }

    if (fieldDefinition) {
      item.component = {
        ...item.component,
        name: fieldDefinition.name,
        props: {
          ...(item.component.props ?? {}),
          ...(fieldDefinition.props ?? {}),
        },
      };
    }

    item.groupName = 'contentOptions';
    item.groupSlug = 'contentOptions';

    if (metadata.name === 'bapf/filters-group' && builtInGroupOnlyFields.has(attrName)) {
      item.show_if = {
        group_id: ['0', ''],
      };
      item.visible = isBuiltInDiviGroupVisible;
    }
  });

  return metadata;
};

const Placeholder = ({ children }) => (
  <div style={{
    padding: '2em 0',
    background: '#6c2eb9',
    color: '#fff',
    fontSize: '12px',
    fontWeight: '600',
    textAlign: 'center',
    borderRadius: '1em',
  }}>
    {children}
  </div>
);

const WidgetPreview = ({
  attrs,
  childFilters,
  metadata,
  moduleId,
  placeholderLabel,
}) => {
  const [state, setState] = useState({
    html: '',
    isLoading: true,
    error: '',
  });
  const [storeVersion, setStoreVersion] = useState(0);
  const attrsKey = useMemo(() => JSON.stringify(attrs ?? {}), [attrs]);
  const childFiltersKey = useMemo(() => JSON.stringify(Array.from(new Set([
    ...(childFilters ?? []),
    ...(metadata.name === 'bapf/filters-group' ? extractFilterIdsFromStore(moduleId) : []),
    ...(metadata.name === 'bapf/filters-group' ? getRegisteredGroupItemFilters() : []),
  ]))), [childFilters, metadata.name, moduleId, storeVersion]);

  useEffect(() => {
    if (metadata.name !== 'bapf/filters-group') {
      return undefined;
    }

    const updateStoreVersion = () => {
      setStoreVersion((version) => version + 1);
    };
    const unsubscribe = subscribeDiviData(updateStoreVersion);

    document.addEventListener('bapf_divi5_group_item_changed', updateStoreVersion);

    return () => {
      unsubscribe();
      document.removeEventListener('bapf_divi5_group_item_changed', updateStoreVersion);
    };
  }, [metadata.name]);

  useEffect(() => {
    const config = previewConfig;
    if (!config?.ajaxUrl || !config?.nonce || !config?.action) {
      setState({
        html: '',
        isLoading: false,
        error: placeholderLabel,
      });
      return undefined;
    }

    const controller = new AbortController();
    const body = new FormData();
    body.append('action', config.action);
    body.append('nonce', config.nonce);
    body.append('module', metadata.name);
    body.append('attrs', attrsKey);
    body.append('filters', childFiltersKey);

    setState((current) => ({
      ...current,
      isLoading: true,
      error: '',
    }));

    fetch(config.ajaxUrl, {
      body,
      method: 'POST',
      credentials: 'same-origin',
      signal: controller.signal,
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response?.success) {
          throw new Error(response?.data?.message || placeholderLabel);
        }

        setState({
          html: response?.data?.html || '',
          isLoading: false,
          error: '',
        });
      })
      .catch((error) => {
        if (error.name === 'AbortError') {
          return;
        }

        setState({
          html: '',
          isLoading: false,
          error: error.message || placeholderLabel,
        });
      });

    return () => controller.abort();
  }, [attrsKey, childFiltersKey, metadata.name, placeholderLabel]);

  useEffect(() => {
    if (!state.isLoading && !state.error && state.html) {
      document.dispatchEvent(new CustomEvent('bapf_update_et_pb_br_filter_single', { bubbles: true }));

      if (typeof window !== 'undefined' && typeof window.braapf_init_load === 'function') {
        window.braapf_init_load();
      }
    }
  }, [state.html, state.isLoading, state.error]);

  if (state.isLoading) {
    return <div className="et-fb-loader-wrapper"><div className="et-fb-loader" /></div>;
  }

  if (state.error || !state.html) {
    return <Placeholder>{state.error || placeholderLabel}</Placeholder>;
  }

  return <div dangerouslySetInnerHTML={{ __html: state.html }} />;
};

const GroupChildVisibilityRuntime = ({ metadata }) => {
  useEffect(() => {
    if (metadata.name === 'bapf/filters-group') {
      ensureGroupChildVisibilityRuntime();
      window.requestAnimationFrame(applyGroupChildVisibility);
    }
  }, [metadata.name]);

  return null;
};

const GroupItemRegistry = ({ attrs, metadata, moduleId }) => {
  useEffect(() => {
    if (metadata.name !== 'bapf/filters-group-item' || !moduleId) {
      return undefined;
    }

    getGroupItemRegistry()[moduleId] = getAttrValue(attrs, 'filter_id');
    document.dispatchEvent(new CustomEvent('bapf_divi5_group_item_changed', { bubbles: true }));

    return () => {
      delete getGroupItemRegistry()[moduleId];
      document.dispatchEvent(new CustomEvent('bapf_divi5_group_item_changed', { bubbles: true }));
    };
  }, [attrs, metadata.name, moduleId]);

  return null;
};

export const createBAPFModule = (metadata, placeholderLabel) => ({
  metadata: normalizeMetadataFields(metadata),
  renderers: {
    edit: ({
      attrs,
      content,
      id,
      name,
      elements,
      children,
    }) => (
      <ModuleContainer
        attrs={attrs}
        elements={elements}
        id={id}
        moduleClassName={metadata.moduleClassName}
        name={name}
        scriptDataComponent={ModuleScriptData}
        stylesComponent={ModuleStyles}
        classnamesFunction={moduleClassnames}
      >
        {elements.styleComponents({
          attrName: 'module',
        })}
        <GroupChildVisibilityRuntime metadata={metadata} />
        <GroupItemRegistry attrs={attrs} metadata={metadata} moduleId={id} />
        <div className="et_pb_module_inner">
          {metadata.name === 'bapf/filter-next' ? (
            <Placeholder>Next products query will be filtered(query must use WooCommerce shortcode hooks)</Placeholder>
          ) : (
            <WidgetPreview
              attrs={attrs}
              childFilters={metadata.name === 'bapf/filters-group' ? extractFilterIdsFromChildren(content ?? children) : []}
              metadata={metadata}
              moduleId={id}
              placeholderLabel={placeholderLabel}
            />
          )}
        </div>
      </ModuleContainer>
    ),
  },
  placeholderContent: {
    module: {
      meta: {
        adminLabel: {
          desktop: {
            value: metadata.title,
          },
        },
      },
    },
    ...metadata.defaultAttrs,
  },
});
