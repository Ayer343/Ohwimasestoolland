/* ============================================================
   SHARED DESIGN TOKENS
   Every --*-rgb is a comma-separated triplet so you can use
   rgba(var(--token-rgb), alpha) anywhere.
   ============================================================ */
:root {
    --primary-rgb:   59, 130, 246;
    --secondary-rgb: 107, 114, 128;
    --success-rgb:   16, 185, 129;
    --warning-rgb:   234, 179, 8;
    --danger-rgb:    239, 68, 68;
    --info-rgb:      14, 165, 233;

    --primary:   rgb(var(--primary-rgb));
    --secondary: rgb(var(--secondary-rgb));
    --success:   rgb(var(--success-rgb));
    --warning:   rgb(var(--warning-rgb));
    --danger:    rgb(var(--danger-rgb));
    --info:      rgb(var(--info-rgb));

    --card-bg:        #ffffff;
    --bg-secondary:   #f9fafb;
    --border-color:   #e5e7eb;
    --text-primary:   #111827;
    --text-secondary: #6b7280;
    --radius-lg:      12px;
    --radius-md:      8px;
}

.dark {
    --card-bg:        #1f2937;
    --bg-secondary:   #111827;
    --border-color:   #374151;
    --text-primary:   #f9fafb;
    --text-secondary: #9ca3af;
}

/* ============================================================
   BASE COMPONENTS
   ============================================================ */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}

.stat-card { padding: 1.25rem; transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }

/* Tinted stat-card backgrounds */
.stat-primary  { background-color: rgba(var(--primary-rgb), .06); }
.stat-success  { background-color: rgba(var(--success-rgb), .06); }
.stat-warning  { background-color: rgba(var(--warning-rgb), .06); }
.stat-danger   { background-color: rgba(var(--danger-rgb),  .06); }
.stat-info     { background-color: rgba(var(--info-rgb),    .06); }

/* ============================================================
   TEXT UTILITIES
   ============================================================ */
.text-primary   { color: var(--text-primary); }
.text-secondary { color: var(--text-secondary); }
.text-success   { color: var(--success); }
.text-warning   { color: var(--warning); }
.text-danger    { color: var(--danger); }
.text-info      { color: var(--info); }

/* ============================================================
   ICON CIRCLES
   ============================================================ */
.icon-circle-primary,
.icon-circle-success,
.icon-circle-warning,
.icon-circle-danger,
.icon-circle-info {
    background-color: rgba(var(--primary-rgb), .1);
    color: var(--primary);
}
.icon-circle-primary { background-color: rgba(var(--primary-rgb), .1); color: var(--primary); }
.icon-circle-success { background-color: rgba(var(--success-rgb), .1); color: var(--success); }
.icon-circle-warning { background-color: rgba(var(--warning-rgb), .1); color: var(--warning); }
.icon-circle-danger  { background-color: rgba(var(--danger-rgb),  .1); color: var(--danger);  }
.icon-circle-info    { background-color: rgba(var(--info-rgb),    .1); color: var(--info);    }

.icon-primary { color: var(--primary); }
.icon-success { color: var(--success); }
.icon-warning { color: var(--warning); }
.icon-danger  { color: var(--danger);  }
.icon-info    { color: var(--info);    }

/* ============================================================
   PILLS / BADGES
   ============================================================ */
.pill {
    display: inline-flex;
    align-items: center;
    padding: .125rem .5rem;
    border-radius: 9999px;
    font-size: .75rem;
    font-weight: 500;
    border: 1px solid transparent;
    line-height: 1.4;
}
.pill-primary   { background-color: rgba(var(--primary-rgb),   .1); color: var(--primary);   border-color: rgba(var(--primary-rgb),   .3); }
.pill-secondary { background-color: rgba(var(--secondary-rgb), .1); color: var(--secondary); border-color: rgba(var(--secondary-rgb), .3); }
.pill-success   { background-color: rgba(var(--success-rgb),   .1); color: var(--success);   border-color: rgba(var(--success-rgb),   .3); }
.pill-warning   { background-color: rgba(var(--warning-rgb),   .1); color: var(--warning);   border-color: rgba(var(--warning-rgb),   .3); }
.pill-danger    { background-color: rgba(var(--danger-rgb),    .1); color: var(--danger);    border-color: rgba(var(--danger-rgb),    .3); }
.pill-info      { background-color: rgba(var(--info-rgb),      .1); color: var(--info);      border-color: rgba(var(--info-rgb),      .3); }

.status-dot { font-size: .5rem; }
.status-dot-success { color: var(--success); }
.status-dot-warning { color: var(--warning); }
.status-dot-danger  { color: var(--danger);  }
.status-dot-info    { color: var(--info);    }

/* ============================================================
   FLASH BANNERS
   ============================================================ */
.flash-success { background-color: rgba(var(--success-rgb), .1); border: 1px solid rgba(var(--success-rgb), .3); }
.flash-warning { background-color: rgba(var(--warning-rgb), .1); border: 1px solid rgba(var(--warning-rgb), .3); }
.flash-danger,
.flash-error   { background-color: rgba(var(--danger-rgb),  .1); border: 1px solid rgba(var(--danger-rgb),  .3); }
.flash-info    { background-color: rgba(var(--info-rgb),    .1); border: 1px solid rgba(var(--info-rgb),    .3); }

/* ============================================================
   BUTTONS
   ============================================================ */
.btn-primary, .btn-secondary, .btn-warning, .btn-info, .btn-danger,
.btn-tenant, .btn-trash, .btn-export,
.btn-soft-primary, .btn-soft-success, .btn-soft-warning,
.btn-soft-danger, .btn-soft-info {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .625rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: .875rem;
    line-height: 1;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease, opacity .15s ease;
}
.btn-primary:hover, .btn-secondary:hover, .btn-warning:hover,
.btn-info:hover, .btn-danger:hover, .btn-tenant:hover,
.btn-trash:hover, .btn-export:hover { transform: translateY(-1px); }

.btn-primary   { background-color: var(--primary);   color: #fff; }
.btn-secondary { background-color: var(--secondary); color: #fff; }
.btn-warning   { background-color: var(--warning);   color: #fff; }
.btn-info      { background-color: var(--info);      color: #fff; }
.btn-danger    { background-color: var(--danger);    color: #fff; }
.btn-tenant    { background-color: rgb(147, 51, 234); color: #fff; }
.btn-export    { background-color: #dc2626;          color: #fff; }
.btn-trash     { background-color: rgba(var(--warning-rgb), .1); color: var(--warning); }

/* Soft variants used for secondary actions in cards */
.btn-soft-primary { background-color: rgba(var(--primary-rgb), .1); color: var(--primary); border-color: rgba(var(--primary-rgb), .3); }
.btn-soft-success { background-color: rgba(var(--success-rgb), .1); color: var(--success); border-color: rgba(var(--success-rgb), .3); }
.btn-soft-warning { background-color: rgba(var(--warning-rgb), .1); color: var(--warning); border-color: rgba(var(--warning-rgb), .3); }
.btn-soft-danger  { background-color: rgba(var(--danger-rgb),  .1); color: var(--danger);  border-color: rgba(var(--danger-rgb),  .3); }
.btn-soft-info    { background-color: rgba(var(--info-rgb),    .1); color: var(--info);    border-color: rgba(var(--info-rgb),    .3); }

/* ============================================================
   FORMS
   ============================================================ */
.form-input, .form-select {
    width: 100%;
    padding: .5rem .75rem;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: .875rem;
    transition: border-color .15s, box-shadow .15s;
}
.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), .15);
}

/* ============================================================
   TABLES
   ============================================================ */
.table { width: 100%; border-collapse: collapse; }
.table-th {
    text-align: left;
    padding: .75rem;
    font-size: .8125rem;
    font-weight: 500;
    color: var(--text-secondary);
    background-color: rgba(var(--primary-rgb), .04);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.table-td {
    padding: .75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}
.invoice-row:hover { background-color: rgba(var(--primary-rgb), .02); }
.row-consolidated  { opacity: .75; background-color: rgba(var(--info-rgb), .02); }
.child-invoice     { border-left: 2px solid var(--info); }

/* ============================================================
   ACTION BUTTONS (table cells)
   ============================================================ */
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    font-size: .8125rem;
    transition: transform .15s ease, opacity .15s ease;
}
.action-btn:hover { transform: translateY(-1px); opacity: .85; }
.action-primary   { background-color: rgba(var(--primary-rgb),   .1); color: var(--primary);   }
.action-secondary { background-color: rgba(var(--secondary-rgb), .1); color: var(--secondary); }
.action-success   { background-color: rgba(var(--success-rgb),   .1); color: var(--success);   }
.action-warning   { background-color: rgba(var(--warning-rgb),   .1); color: var(--warning);   }
.action-danger    { background-color: rgba(var(--danger-rgb),    .1); color: var(--danger);    }
.action-info      { background-color: rgba(var(--info-rgb),      .1); color: var(--info);      }

/* ============================================================
   LINKS
   ============================================================ */
.link-inline {
    display: inline-flex;
    align-items: center;
    color: var(--primary);
    font-size: .875rem;
    font-weight: 500;
    text-decoration: none;
}
.link-inline:hover { text-decoration: underline; }
.link-info { color: var(--info); text-decoration: none; }
.link-info:hover { text-decoration: underline; }

/* ============================================================
   MODAL BACKDROP (used by every modal in this page)
   ============================================================ */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, .5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    transition: opacity .2s ease;
}
.modal-backdrop.hidden {
    display: none;
}

/* ============================================================
   SPINNER
   ============================================================ */
@keyframes spin { to { transform: rotate(360deg); } }
.animate-spin { animation: spin 1s linear infinite; }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 768px) {
    .table { font-size: .8125rem; }
    .table-th, .table-td { padding: .5rem; }
    .btn-primary, .btn-secondary, .btn-warning, .btn-info,
    .btn-danger, .btn-tenant, .btn-trash, .btn-export {
        padding: .5rem 1rem;
        font-size: .8125rem;
    }
    .stat-card .text-2xl { font-size: 1.25rem; }
}