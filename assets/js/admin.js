/**
 * TravelMap Admin Panel JavaScript
 * Handles sidebar toggle, tabs, and common interactions
 */

(function() {
    'use strict';

    // DOM Elements
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Sidebar state from localStorage
    const SIDEBAR_COLLAPSED_KEY = 'admin_sidebar_collapsed';
    
    /**
     * Initialize sidebar state from localStorage
     */
    function initSidebar() {
        if (!sidebar) return;
        
        const isCollapsed = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true';
        if (isCollapsed && window.innerWidth > 992) {
            sidebar.classList.add('collapsed');
            document.documentElement.classList.add('sidebar-collapsed');
        } else {
            // Remove html class if not collapsed (in case it was set by inline script)
            document.documentElement.classList.remove('sidebar-collapsed');
        }
    }
    
    /**
     * Toggle sidebar collapsed state (desktop)
     */
    function toggleSidebarCollapse() {
        if (!sidebar) return;
        
        sidebar.classList.toggle('collapsed');
        document.documentElement.classList.toggle('sidebar-collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem(SIDEBAR_COLLAPSED_KEY, isCollapsed);
    }
    
    /**
     * Toggle mobile sidebar
     */
    function toggleMobileSidebar() {
        if (!sidebar || !sidebarOverlay) return;
        
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }
    
    /**
     * Close mobile sidebar
     */
    function closeMobileSidebar() {
        if (!sidebar || !sidebarOverlay) return;
        
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Event Listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebarCollapse);
    }
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleMobileSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
    }
    
    
    // Close mobile sidebar on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            closeMobileSidebar();
        }
    });
    
    // Initialize sidebar
    initSidebar();
    
    /**
     * Auto-dismiss alerts after 5 seconds
     */
    function initAlertDismiss() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            }, 5000);
        });
    }
    
    initAlertDismiss();
    
    /**
     * Confirmation before delete actions
     */
    document.querySelectorAll('.btn-delete, .delete-action').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || '¿Estás seguro de que deseas eliminar este elemento? Esta acción no se puede deshacer.';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    /**
     * Initialize Bootstrap tooltips if available
     */
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    /**
     * Settings Tabs functionality (if on settings page)
     */
    function initSettingsTabs() {
        const tabs = document.querySelectorAll('.admin-tabs .tab-link');
        const contents = document.querySelectorAll('.tab-content');
        
        if (tabs.length === 0) return;
        
        // Check URL hash for initial tab
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            const targetTab = document.querySelector(`.tab-link[data-tab="${hash}"]`);
            if (targetTab) {
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                targetTab.classList.add('active');
                const content = document.getElementById('tab-' + hash);
                if (content) content.classList.add('active');
            }
        }
        
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked
                this.classList.add('active');
                const content = document.getElementById('tab-' + tabId);
                if (content) content.classList.add('active');
                
                // Update URL hash without scrolling
                history.replaceState(null, null, '#' + tabId);
            });
        });
    }
    
    initSettingsTabs();
    
    /**
     * Form validation visual feedback
     */
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const invalidInputs = form.querySelectorAll(':invalid');
            if (invalidInputs.length > 0) {
                invalidInputs.forEach(function(input) {
                    input.style.borderColor = 'var(--admin-danger)';
                    input.addEventListener('input', function() {
                        if (this.validity.valid) {
                            this.style.borderColor = '';
                        }
                    }, { once: true });
                });
            }
        });
    });
    
    /**
     * Smooth scroll for anchor links
     */
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    /**
     * Keyboard shortcuts
     */
    document.addEventListener('keydown', function(e) {
        // ESC to close mobile sidebar
        if (e.key === 'Escape') {
            closeMobileSidebar();
        }
        
        // Ctrl/Cmd + S to save form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            const form = document.querySelector('form');
            if (form) {
                e.preventDefault();
                form.submit();
            }
        }
    });
    
    console.log('TravelMap Admin initialized');
})();
