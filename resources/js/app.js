import './bootstrap';
import './bid-events';
import './bid-ui';
import './toast';
import './filepond-setup';

import Alpine from 'alpinejs';

import TomSelect from 'tom-select';
window.TomSelect = TomSelect;

window.Alpine = Alpine;

Alpine.start();
