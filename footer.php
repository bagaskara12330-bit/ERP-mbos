    <footer class="footer-premium">
        <div class="footer-brand">
            <div class="footer-logo">H2</div>
            <span>&copy; <?= date('Y') ?> <strong>H2 BASE ERP</strong> &mdash; Manufacturing Core</span>
        </div>
        <div class="footer-status">
            <span class="status-indicator"><span class="dot-green"></span> System Online</span>
            <span class="divider">|</span>
            <span class="version-badge">v2.0</span>
        </div>
    </footer>
</div> <!-- /container -->

<style>
    .footer-premium {
        margin-top: 40px; 
        padding: 24px 0; 
        border-top: 1px dashed #cbd5e1; 
        display: flex; 
        flex-wrap: wrap; 
        justify-content: space-between; 
        align-items: center; 
        color: #64748b; 
        font-size: 13px; 
        font-weight: 500;
    }
    .footer-brand { display: flex; align-items: center; gap: 10px; }
    .footer-brand strong { color: #0f172a; font-weight: 800; }
    .footer-logo { width: 24px; height: 24px; background: #0f172a; border-radius: 6px; display: flex; justify-content: center; align-items: center; color: white; font-weight: 900; font-size: 11px; letter-spacing: -0.5px; }
    
    .footer-status { display: flex; gap: 16px; align-items: center; }
    .status-indicator { display: flex; align-items: center; gap: 6px; font-weight: 600; color: #475569;}
    .dot-green { width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); animation: pulseGreen 2s infinite; }
    .divider { opacity: 0.3; }
    @media (max-width: 768px) {
        .footer-premium { flex-direction: column; text-align: center; gap: 16px; padding-bottom: 30px;}
        .footer-brand, .footer-status { justify-content: center; width: 100%; }
        .footer-status { gap: 12px; }
    }
</style>
</body>
</html>