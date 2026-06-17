import axios from 'axios';
window.axios = axios;

window.axios.defaults.baseURL = window.APP_BASE ?? '';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
