/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

declare global {
    interface Window {
        Echo: Echo<'reverb'>;
        Pusher: typeof Pusher;
    }
}

window.Pusher = Pusher;

/**
 * Note that these are REVERB_HOST and REVERB_PORT rather than REVERB_SERVER_HOST and REVERB_SERVER_PORT. The latter
 * pair is the address Reverb binds to, which is 0.0.0.0: every interface at once. That isn't somewhere a browser can
 * connect to, so what's wanted here is the address Reverb is reachable at, which is the same one the back end uses to
 * broadcast in config/broadcasting.php.
 */
const options = {
    broadcaster: 'reverb' as const,
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
};

window.Echo = new Echo(options);
