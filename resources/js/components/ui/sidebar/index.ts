import { cva, type VariantProps } from 'class-variance-authority';

export { default as Sidebar } from './Sidebar.vue';
export { default as SidebarContent } from './SidebarContent.vue';
export { default as SidebarFooter } from './SidebarFooter.vue';
export { default as SidebarGroup } from './SidebarGroup.vue';
export { default as SidebarGroupAction } from './SidebarGroupAction.vue';
export { default as SidebarGroupContent } from './SidebarGroupContent.vue';
export { default as SidebarGroupLabel } from './SidebarGroupLabel.vue';
export { default as SidebarHeader } from './SidebarHeader.vue';
export { default as SidebarInput } from './SidebarInput.vue';
export { default as SidebarInset } from './SidebarInset.vue';
export { default as SidebarMenu } from './SidebarMenu.vue';
export { default as SidebarMenuAction } from './SidebarMenuAction.vue';
export { default as SidebarMenuBadge } from './SidebarMenuBadge.vue';
export { default as SidebarMenuButton } from './SidebarMenuButton.vue';
export { default as SidebarMenuItem } from './SidebarMenuItem.vue';
export { default as SidebarMenuSkeleton } from './SidebarMenuSkeleton.vue';
export { default as SidebarMenuSub } from './SidebarMenuSub.vue';
export { default as SidebarMenuSubButton } from './SidebarMenuSubButton.vue';
export { default as SidebarMenuSubItem } from './SidebarMenuSubItem.vue';
export { default as SidebarProvider } from './SidebarProvider.vue';
export { default as SidebarRail } from './SidebarRail.vue';
export { default as SidebarSeparator } from './SidebarSeparator.vue';
export { default as SidebarTrigger } from './SidebarTrigger.vue';

export { useSidebar } from './utils';

export const sidebarMenuButtonVariants = cva(
    'peer/menu-button flex w-full items-center gap-3 overflow-hidden rounded-md text-left outline-none ring-sidebar-ring transition-[width,height,padding,background-color,color] duration-200 ease-out hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 group-has-[[data-sidebar=menu-action]]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:!size-9 [&>span:last-child]:truncate [&>svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                outline:
                    'bg-background shadow-[0_0_0_1px_hsl(var(--sidebar-border))] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground hover:shadow-[0_0_0_1px_hsl(var(--sidebar-accent))]',
            },
            size: {
                // Nav links: a 44px tap target in the mobile sheet. On desktop the rows,
                // text and icons grow to fill the panel's height (the --nav-* sizes in
                // app.css); the fallbacks are for rows outside the nav, like the agent's
                // footer. The collapsed rail shows only icons, so they grow to fill a 36px
                // square (and swell on hover: the dock rules in app.css).
                default:
                    'h-11 px-2.5 text-base md:h-[var(--nav-row,2.125rem)] md:text-[length:var(--nav-text,1.125rem)] [&>svg]:size-5 md:[&>svg]:size-[var(--nav-icon,1.25rem)] group-data-[collapsible=icon]:!p-0.5 group-data-[collapsible=icon]:[&>span]:hidden group-data-[collapsible=icon]:[&>svg]:size-8',
                sm: 'h-7 p-2 text-xs [&>svg]:size-4',
                lg: 'h-12 p-2 text-sm group-data-[collapsible=icon]:!p-0.5',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type SidebarMenuButtonVariants = VariantProps<typeof sidebarMenuButtonVariants>;
