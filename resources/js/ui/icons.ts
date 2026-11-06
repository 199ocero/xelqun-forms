import {
    AtSign,
    Building,
    Calendar,
    Check,
    CircleAlert,
    Clock,
    CreditCard,
    DollarSign,
    Eye,
    File,
    Folder,
    Globe,
    Hash,
    Heart,
    Image,
    Info,
    Key,
    Link,
    Lock,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Search,
    Settings,
    Star,
    Tag,
    ThumbsDown,
    ThumbsUp,
    Trash,
    User,
    Users,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * shortcut: a fixed set, because any dynamic import of lucide would pull all of its
 * icons into the main bundle. Apps add more with the `icons` option.
 */
const icons: Record<string, Component> = {
    'at-sign': AtSign,
    building: Building,
    calendar: Calendar,
    check: Check,
    'circle-alert': CircleAlert,
    clock: Clock,
    'credit-card': CreditCard,
    'dollar-sign': DollarSign,
    eye: Eye,
    file: File,
    folder: Folder,
    globe: Globe,
    hash: Hash,
    heart: Heart,
    image: Image,
    info: Info,
    key: Key,
    link: Link,
    lock: Lock,
    mail: Mail,
    'map-pin': MapPin,
    pencil: Pencil,
    phone: Phone,
    search: Search,
    settings: Settings,
    star: Star,
    tag: Tag,
    'thumbs-down': ThumbsDown,
    'thumbs-up': ThumbsUp,
    trash: Trash,
    user: User,
    users: Users,
    x: X,
};

/**
 * Add icons that PHP can name, as { name: component }.
 */
export function registerIcons(extra: Record<string, Component>): void {
    Object.assign(icons, extra);
}

export function resolveIcon(name: string): Component | null {
    return icons[name] ?? null;
}
