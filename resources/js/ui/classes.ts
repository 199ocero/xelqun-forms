/**
 * Shared class strings for form controls. Components use only --xq-* token
 * utilities, so they never collide with an app's own design tokens.
 */
export const controlClass =
    'w-full min-w-0 rounded-xq-md border border-xq-input bg-xq-background text-sm text-xq-foreground shadow-xs transition-[color,box-shadow,border-color] outline-none placeholder:text-xq-muted-foreground focus-visible:border-xq-ring focus-visible:ring-[3px] focus-visible:ring-xq-ring/25 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-xq-destructive aria-invalid:ring-xq-destructive/20';

export const spinnerClass = 'size-4 shrink-0 motion-safe:animate-spin text-xq-muted-foreground';

export const popoverClass =
    'z-50 overflow-hidden rounded-xq-md border border-xq-border bg-xq-popover text-xq-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0';

export const groupLabelClass = 'px-2 py-1.5 text-xs font-medium text-xq-muted-foreground';

export const optionClass =
    'relative flex w-full cursor-default items-center gap-2 rounded-xq-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-xq-accent data-[highlighted]:text-xq-accent-foreground';

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
