import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global { interface Window { Pusher?: typeof Pusher } }
if (typeof window !== 'undefined') window.Pusher = Pusher;

/* eslint-disable @typescript-eslint/no-explicit-any */
let echo: Echo<any> | null = null;

export function getEcho(): Echo<any> | null {
  if (echo) return echo;
  try {
    echo = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_WS_KEY ?? 'ciphersoc-key',
      wsHost: import.meta.env.VITE_WS_HOST ?? '127.0.0.1',
      wsPort: Number(import.meta.env.VITE_WS_PORT ?? 8080),
      forceTLS: false,
      enabledTransports: ['ws'],
      authEndpoint: `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'}/broadcasting/auth`,
      auth: { headers: { Authorization: `Bearer ${localStorage.getItem('ciphersoc_token') ?? ''}` } },
    });
  } catch { echo = null; }
  return echo;
}
