<style>
    /* Keep alert-like badges (navigation + notifications) on the same red style. */
    .fi-nav-item-alert-badge .fi-sidebar-item-badge-ctn .fi-badge,
    .fi-topbar-database-notifications-btn .fi-badge,
    .fi-no-database .fi-modal-heading .fi-badge {
        background-color: rgb(255, 0, 0) !important;
        color: #ffffff !important;
        /* Perfect circle for 1-digit; pill for 2+ digits. */
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        height: 1.25rem !important;
        min-width: 1.25rem !important;
        line-height: 1 !important;
        font-variant-numeric: tabular-nums;
        padding: 0 0.4rem !important;
        border-radius: 9999px !important;
    }
</style>
