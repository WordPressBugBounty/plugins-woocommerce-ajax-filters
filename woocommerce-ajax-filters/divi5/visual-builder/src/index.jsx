import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { createBAPFModule } from './module-factory';

import singleFilterMetadata from './modules/single-filter/module.json';
import filtersGroupMetadata from './modules/filters-group/module.json';
import filterNextMetadata from './modules/filter-next/module.json';
import filtersGroupItemMetadata from './modules/filters-group-item/module.json';
import sidebarButtonMetadata from './modules/sidebar-button/module.json';

const modules = [
  [singleFilterMetadata, 'Single Filter not displayed in Builder'],
  [filtersGroupMetadata, 'Filter Group not displayed in Builder'],
  [filterNextMetadata, 'Next products query will be filtered(query must use WooCommerce shortcode hooks)'],
  [filtersGroupItemMetadata, 'Filter group item'],
  [sidebarButtonMetadata, 'Sidebar button not displayed in Builder'],
];

addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'bapf.divi5Modules', () => {
  modules.forEach(([metadata, placeholderLabel]) => {
    const module = createBAPFModule(metadata, placeholderLabel);
    const { metadata: moduleMetadata, ...moduleConfig } = module;
    registerModule(moduleMetadata, moduleConfig);
  });
});
