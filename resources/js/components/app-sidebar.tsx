import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type Chat, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, MessageSquare } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    // Get chats from the page props
    const pageProps = usePage().props as { chats?: Chat[] };
    const chats = Array.isArray(pageProps.chats) ? pageProps.chats : [];

    // Create navigation items for each chat
    const chatNavItems: NavItem[] = chats.map((chat: Chat) => ({
        title: chat.name || `${new Date(chat.created_at).toLocaleTimeString()} - ${new Date(chat.created_at).toLocaleDateString()}`,
        url: `/dashboard/${chat.id}`,
        icon: MessageSquare,
    }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="max-h-[calc(100vh-200px)] overflow-y-auto">
                {chatNavItems.length > 0 ? (
                    <NavMain items={chatNavItems} />
                ) : (
                    <div className="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No chats yet. Start a new conversation!</div>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
