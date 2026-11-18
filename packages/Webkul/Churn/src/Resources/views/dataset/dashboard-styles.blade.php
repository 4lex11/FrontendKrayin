<style>
    .sales-dashboard {
        --sales-ink: #172740; --sales-muted: #52647d; --sales-surface: #fff;
        --sales-border: #dce5ef; --sales-accent: #1474ae; --sales-track: #edf3f7;
        display: grid; gap: 16px; min-width: 0; color: var(--sales-ink);
        container: sales / inline-size; font-variant-numeric: tabular-nums;
    }
    .dark .sales-dashboard {
        --sales-ink: #e8f0fa; --sales-muted: #b1c0d5; --sales-surface: #17263b;
        --sales-border: #344862; --sales-accent: #69bbf0; --sales-track: #263b52;
    }
    .sales-panel { background: var(--sales-surface); border: 1px solid var(--sales-border); border-radius: 12px; min-width: 0; }
    .sales-heading, .sales-filter { display: flex; flex-wrap: wrap; align-items: end; gap: 12px 20px; justify-content: space-between; }
    .sales-heading { padding: 5px 1px; }
    .sales-heading h1 { font-size: clamp(1.5rem, 2vw, 2.1rem); font-weight: 750; line-height: 1.18; }
    .sales-heading p { margin-top: 5px; }
    .sales-heading p, .sales-dashboard p, .sales-kpi dt { color: var(--sales-muted); font-size: .84rem; }
    .sales-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
    .sales-button, .sales-link { display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 7px; font-weight: 650; font-size: .82rem; }
    .sales-button { background: #12669b; color: white; }
    .sales-button:hover { background: #0b527e; }
    .sales-link { color: var(--sales-accent); text-decoration: underline; text-underline-offset: .2em; }
    .sales-dashboard :focus-visible { outline: 2px solid var(--sales-accent); outline-offset: 3px; }
    .sales-filter { padding: 14px; }
    .sales-filter-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 137px), 1fr)); gap: 9px; flex: 1 1 560px; min-width: 0; }
    .sales-filter-fields label { color: var(--sales-muted); margin-bottom: 4px; }
    .sales-filter-fields input, .sales-filter-fields select { width: 100%; min-width: 0; border-color: var(--sales-border); color-scheme: light; }
    .dark .sales-filter-fields input, .dark .sales-filter-fields select { color-scheme: dark; }
    .sales-kpis { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); overflow: hidden; }
    .sales-kpi { padding: 13px 17px; border: 1px solid var(--sales-border); border-width: 0 1px 1px 0; min-width: 0; }
    .sales-kpi dd { font-size: clamp(1.2rem, 2vw, 1.65rem); font-weight: 750; overflow-wrap: anywhere; }
    .sales-kpi-primary { grid-column: 1 / -1; background: #143251; color: #fff; }
    .sales-kpi-primary dt, .sales-kpi-primary .sales-period { color: #d8e7f3; }
    .sales-kpi-primary dd { font-size: clamp(1.55rem, 2.5vw, 2.15rem); }
    .sales-kpi-primary .sales-period { font-weight: 400; font-size: .76rem; margin-top: 2px; }
    .sales-overview, .sales-breakdown { display: grid; gap: 14px; grid-template-columns: minmax(0,1fr); }
    .sales-chart, .sales-predictions, .sales-empty { padding: 15px; }
    .sales-chart h2, .sales-predictions h2 { font-size: 1.05rem; font-weight: 740; margin-bottom: 9px; }
    .sales-monthly-scroll { overflow-x: auto; padding: 7px 4px 0 0; }
    .sales-monthly-plot { display: grid; grid-template-columns: 57px minmax(0,1fr); gap: 7px; min-width: 370px; padding: 5px 0 25px; }
    .sales-axis { height: 154px; display: flex; flex-direction: column; justify-content: space-between; color: var(--sales-muted); font-size: .7rem; text-align: right; }
    .sales-months {
        display: grid; grid-template-columns: repeat(var(--months), minmax(0,1fr)); gap: 2px; height: 154px;
        border-bottom: 1px solid var(--sales-border);
        background: repeating-linear-gradient(to top, transparent 0 calc(25% - 1px), var(--sales-border) calc(25% - 1px) 25%);
    }
    .sales-month { position: relative; min-width: 0; }
    .sales-month-slot { display: flex; align-items: end; justify-content: center; height: 100%; }
    .sales-month-bar { display: block; width: min(100%, 22px); border-radius: 3px 3px 0 0; background: var(--sales-accent); }
    .sales-month-slot:hover .sales-month-bar { background: #18a08f; }
    .sales-year { position: absolute; top: calc(100% + 6px); left: 0; color: var(--sales-muted); font-size: .7rem; }
    .sales-chart-data { font-size: .8rem; color: var(--sales-muted); }
    .sales-chart-data summary { cursor: pointer; }
    .sales-chart-data dl { display: grid; grid-template-columns: repeat(auto-fit,minmax(145px,1fr)); gap: 3px 16px; margin-top: 8px; max-height: 240px; overflow: auto; }
    .sales-chart-data dl > div { display: flex; justify-content: space-between; gap: 5px; }
    .sales-bars-scroll { max-height: 290px; overflow: auto; }
    .sales-bars { display: grid; gap: 12px; padding: 3px 1px; }
    .sales-bars > div { display: grid; grid-template-columns: 1fr auto; gap: 4px 8px; font-size: .82rem; }
    .sales-bars dd { font-weight: 650; text-align: right; }
    .sales-bar-track { display: block; grid-column: 1 / -1; border-radius: 6px; background: var(--sales-track); height: 7px; overflow: hidden; }
    .sales-bar-track span { display: block; background: var(--sales-accent); border-radius: inherit; height: 100%; }
    .sales-prediction-kpis { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 9px; }
    .sales-prediction-kpis > div { padding: 8px 11px; border-left: 3px solid var(--sales-border); background: var(--sales-track); }
    .sales-prediction-kpis dt { color: var(--sales-muted); font-size: .77rem; }
    .sales-prediction-kpis dd { font-weight: 750; font-size: 1.2rem; }
    .sales-prediction-kpis [data-risk="high_risk"] { border-color: #d95b56; }
    .sales-prediction-kpis [data-risk="medium_risk"] { border-color: #cf932c; }
    .sales-prediction-kpis [data-risk="low_risk"] { border-color: #278d76; }
    @container sales (min-width: 680px) {
        .sales-kpis { grid-template-columns: minmax(190px,1.55fr) repeat(4,minmax(0,1fr)); }
        .sales-kpi-primary { grid-column: auto; }
        .sales-kpi { border-bottom-width: 0; align-content: center; }
        .sales-prediction-kpis { grid-template-columns: repeat(4,minmax(0,1fr)); }
        .sales-breakdown { grid-template-columns: repeat(2,minmax(0,1fr)); }
    }
    @container sales (min-width: 880px) {
        .sales-overview { grid-template-columns: minmax(0,2fr) minmax(0,1fr); }
        .sales-breakdown { grid-template-columns: repeat(3,minmax(0,1fr)); }
    }
</style>
