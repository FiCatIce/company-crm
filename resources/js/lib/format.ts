// Shared display formatters for the CRM (Indonesian locale).

export function formatIdr(value: number | string | null): string {
    const n = Number(value ?? 0);

    if (!Number.isFinite(n) || n === 0) {
        return 'Rp0';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(n);
}

function startOfDay(date: Date): number {
    return new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    ).getTime();
}

function dayDiff(iso: string): number {
    return Math.round(
        (startOfDay(new Date()) - startOfDay(new Date(iso))) / 86_400_000,
    );
}

export function relativeDays(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const diff = dayDiff(iso);

    if (diff <= 0) {
        return 'hari ini';
    }

    if (diff === 1) {
        return 'kemarin';
    }

    if (diff < 30) {
        return `${diff} hari lalu`;
    }

    if (diff < 365) {
        return `${Math.floor(diff / 30)} bulan lalu`;
    }

    return `${Math.floor(diff / 365)} tahun lalu`;
}

export function dayGroupLabel(iso: string): string {
    const diff = dayDiff(iso);

    if (diff <= 0) {
        return 'Hari ini';
    }

    if (diff === 1) {
        return 'Kemarin';
    }

    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export function formatClock(iso: string): string {
    return new Date(iso).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

// 'YYYY-MM-DDTHH:mm' in the viewer's local time, for a datetime-local input.
export function toDatetimeLocal(iso: string | null): string {
    const d = iso ? new Date(iso) : new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatDuration(seconds: number | null): string {
    if (!seconds || seconds <= 0) {
        return '';
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${minutes}:${String(rest).padStart(2, '0')}`;
}
