{{-- کارت‌های آماری داشبورد: بدون وابستگی به بیلد Vite (همیشه در head) --}}
<style id="dashboard-stat-tiles-css">
.dashboard-stat-tile {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 1rem;
    padding: 1.25rem;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.12), 0 4px 6px -4px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: var(--ui-text-inverse) !important;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, 0.12);
}
.dashboard-stat-tile:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18);
}
.dashboard-stat-tile__shine {
    pointer-events: none;
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.14) 0%, transparent 55%);
}
.dashboard-stat-tile__label {
    position: relative;
    z-index: 10;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 600;
    opacity: 0.95;
}
.dashboard-stat-tile__value {
    position: relative;
    z-index: 10;
    margin-top: 0.25rem;
    font-size: 1.875rem;
    line-height: 2.25rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.dashboard-stat-tile__hint {
    position: relative;
    z-index: 10;
    margin-top: 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
    opacity: 0.88;
}
.dashboard-stat-tile--violet {
    background: linear-gradient(135deg, var(--ui-purple) 0%, var(--ui-purple-hover) 100%);
}
.dashboard-stat-tile--sky {
    background: linear-gradient(135deg, var(--ui-info) 0%, var(--ui-info-hover) 100%);
}
.dashboard-stat-tile--emerald {
    background: linear-gradient(135deg, var(--ui-success) 0%, var(--ui-success-hover) 100%);
}
.dashboard-stat-tile--rose {
    background: linear-gradient(135deg, var(--ui-danger) 0%, var(--ui-danger-hover) 100%);
}
.dashboard-stat-tile--amber {
    background: linear-gradient(135deg, var(--ui-warning) 0%, var(--ui-warning-hover) 100%);
}
</style>
