/**
 * Shared class strings for form controls. Components use only --xq-* token
 * utilities, so they never collide with an app's own design tokens.
 */
export const controlClass =
    'w-full min-w-0 rounded-xq-md border border-xq-input bg-xq-background text-sm text-xq-foreground shadow-xs transition-[color,box-shadow,border-color] outline-none placeholder:text-xq-muted-foreground focus-visible:border-xq-ring focus-visible:ring-[3px] focus-visible:ring-xq-ring/25 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-xq-destructive aria-invalid:ring-xq-destructive/20';

/** How an action's color looks on a button. */
export const actionVariants: Record<string, 'default' | 'destructive' | 'outline'> = {
    primary: 'default',
    danger: 'destructive',
    gray: 'outline',
};

export const spinnerClass = 'size-4 shrink-0 motion-safe:animate-spin text-xq-muted-foreground';

export const popoverClass =
    'z-50 overflow-hidden rounded-xq-md border border-xq-border bg-xq-popover text-xq-popover-foreground shadow-lg origin-(--reka-popper-transform-origin) data-[state=open]:animate-xq-pop-in data-[state=closed]:animate-xq-pop-out';

export const groupLabelClass = 'px-2 py-1.5 text-xs font-medium text-xq-muted-foreground';

export const optionClass =
    'relative flex w-full cursor-default items-center gap-2 rounded-xq-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-xq-accent data-[highlighted]:text-xq-accent-foreground';

export const dialogOverlayClass =
    'inset-0 bg-black/50 fixed z-50 backdrop-blur-[2px] data-[state=open]:animate-xq-fade-in data-[state=closed]:animate-xq-fade-out';

export const dialogContentClass =
    'shadow-xl fixed top-1/2 left-1/2 z-50 flex max-h-[85vh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-xq-xl border border-xq-border bg-xq-popover text-xq-popover-foreground outline-none data-[state=open]:animate-xq-dialog-in data-[state=closed]:animate-xq-dialog-out';

/** The tinted circle behind a dialog's icon. */
export const dialogIconClasses: Record<string, string> = {
    primary: 'bg-xq-primary/10 text-xq-primary',
    danger: 'bg-xq-destructive/10 text-xq-destructive',
    info: 'bg-xq-info/10 text-xq-info',
    success: 'bg-xq-success/10 text-xq-success',
    warning: 'bg-xq-warning/10 text-xq-warning',
    gray: 'bg-xq-muted text-xq-muted-foreground',
};

/** Text colors the PHP side can name. */
export const toneClasses: Record<string, string> = {
    neutral: 'text-xq-foreground',
    primary: 'text-xq-primary',
    danger: 'text-xq-destructive',
    info: 'text-xq-info',
    success: 'text-xq-success',
    warning: 'text-xq-warning',
};

export const textSizeClasses: Record<string, string> = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg',
};

export const iconSizeClasses: Record<string, string> = {
    xs: 'size-3',
    sm: 'size-4',
    md: 'size-5',
    lg: 'size-6',
    xl: 'size-8',
    '2xl': 'size-10',
};

export const toolbarButtonClass =
    'size-7 flex cursor-pointer items-center justify-center rounded-xq-sm text-xq-muted-foreground hover:bg-xq-accent aria-pressed:bg-xq-accent aria-pressed:text-xq-accent-foreground disabled:pointer-events-none disabled:opacity-50';
