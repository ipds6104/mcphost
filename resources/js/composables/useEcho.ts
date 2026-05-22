import { onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

export function useEcho() {
    if (typeof window !== 'undefined') {
        if (!window.Pusher) {
            window.Pusher = Pusher;
        }

        if (!window.Echo) {
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
                wsPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 8080,
                wssPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        }
    }

    const activeChannels = new Map<string, ReturnType<typeof window.Echo.private>>();

    const listenPrivate = (
        channelName: string,
        events: Array<{
            name: string;
            callback: (data: any) => void;
        }>
    ) => {
        if (activeChannels.has(channelName)) return;

        const channel = window.Echo.private(channelName);
        events.forEach((event) => {
            channel.listen(event.name, event.callback);
        });

        activeChannels.set(channelName, channel);
    };

    const leaveChannel = (channelName: string) => {
        const channel = activeChannels.get(channelName);
        if (channel) {
            window.Echo.leave(channelName);
            activeChannels.delete(channelName);
        }
    };

    onUnmounted(() => {
        activeChannels.forEach((_, channelName) => {
            window.Echo.leave(channelName);
        });
        activeChannels.clear();
    });

    return {
        echo: typeof window !== 'undefined' ? window.Echo : null,
        listenPrivate,
        leaveChannel,
    };
}
