/// <reference types="vite/client" />

declare global {
    interface RouteFunction {
        (): {
            current(name?: string, params?: any): boolean;
            [key: string]: any;
        };
        (
            name: string,
            params?: any,
            absolute?: boolean,
            config?: any
        ): string;
        current(name?: string, params?: any): boolean;
    }
    const route: RouteFunction;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: {
                id: string;
                name: string;
                email: string;
                email_verified_at?: string;
            };
        };
    }
}

export {};


