import {
    AtSign,
    Building,
    Calendar,
    Check,
    CircleAlert,
    CircleCheck,
    Clock,
    CreditCard,
    DollarSign,
    EllipsisVertical,
    Eye,
    File,
    Folder,
    Globe,
    Hash,
    Heart,
    Image,
    Inbox,
    Info,
    Key,
    Link,
    Lock,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Search,
    Settings,
    Star,
    Tag,
    ThumbsDown,
    ThumbsUp,
    Trash,
    TriangleAlert,
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
    'circle-check': CircleCheck,
    clock: Clock,
    'credit-card': CreditCard,
    'dollar-sign': DollarSign,
    'ellipsis-vertical': EllipsisVertical,
    eye: Eye,
    file: File,
    folder: Folder,
    globe: Globe,
    hash: Hash,
    heart: Heart,
    image: Image,
    inbox: Inbox,
    info: Info,
    key: Key,
    link: Link,
    lock: Lock,
    mail: Mail,
    'map-pin': MapPin,
    pencil: Pencil,
    phone: Phone,
    plus: Plus,
    search: Search,
    settings: Settings,
    star: Star,
    tag: Tag,
    'thumbs-down': ThumbsDown,
    'thumbs-up': ThumbsUp,
    trash: Trash,
    'triangle-alert': TriangleAlert,
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
