export function route(name: string, params?: any): string {
    return (window as any).route(name, params);
}
