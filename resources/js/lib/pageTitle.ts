/**
 * The browser-tab (and search-result) title. RoutePilot's own pages end in
 * "- RoutePilot"; a company's site belongs to that company, so its title is its own.
 */
export function pageTitle(title: string, component: string | undefined, appName: string): string {
    return component === 'public/Landing' ? title : `${title} - ${appName}`;
}
