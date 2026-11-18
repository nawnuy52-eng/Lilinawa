// Admin/assets/js/admin.js - Interaktivitas Dashboard Admin LilinNawa
document.addEventListener('DOMContentLoaded', function() {
    console.log('LN Digital Admin Workspace Initialized');

    // Auto dismiss flash alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 400);
        }, 4000);
    });

    // Close modal on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.style.display = 'none';
            }
        });
    });

    // Close modal on escape key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(function(m) {
                m.style.display = 'none';
            });
        }
    });
});

// Modal helpers
window.openModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
    }
};

window.closeModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
};

window.openEditModal = function(order) {
    if (!order) return;
    if (document.getElementById('edit_order_id')) document.getElementById('edit_order_id').value = order.id || '';
    if (document.getElementById('edit_order_number')) document.getElementById('edit_order_number').value = order.order_number || '';
    if (document.getElementById('edit_customer_name')) document.getElementById('edit_customer_name').value = order.customer_name || '';
    if (document.getElementById('edit_customer_phone')) document.getElementById('edit_customer_phone').value = order.customer_phone || '';
    if (document.getElementById('edit_status')) document.getElementById('edit_status').value = order.status || 'pilih_template';
    if (document.getElementById('edit_step_index')) document.getElementById('edit_step_index').value = order.step_index !== undefined ? order.step_index : 0;
    if (document.getElementById('edit_payment_status')) document.getElementById('edit_payment_status').value = order.payment_status || 'pending';
    if (document.getElementById('edit_total_price')) document.getElementById('edit_total_price').value = order.total_price || 0;
    if (document.getElementById('edit_result_link')) document.getElementById('edit_result_link').value = order.result_link || '';
    if (document.getElementById('edit_nama_acara')) document.getElementById('edit_nama_acara').value = order.nama_acara || '';
    if (document.getElementById('edit_tanggal_acara')) document.getElementById('edit_tanggal_acara').value = order.tanggal_acara || '';
    if (document.getElementById('edit_lokasi_acara')) document.getElementById('edit_lokasi_acara').value = order.lokasi_acara || '';
    window.openModal('modalEditOrder');
};

window.syncStepIndex = function(status) {
    const stepMap = {
        'pilih_template': 0,
        'konsultasi': 1,
        'data_terkirim': 2,
        'diproses': 3,
        'undangan_jadi': 4,
        'dikirim': 5
    };
    if (stepMap[status] !== undefined && document.getElementById('edit_step_index')) {
        document.getElementById('edit_step_index').value = stepMap[status];
    }
};

window.openReplyModal = function(id, currentReply) {
    if (document.getElementById('reply_review_id')) document.getElementById('reply_review_id').value = id;
    if (document.getElementById('reply_admin_text')) document.getElementById('reply_admin_text').value = currentReply || '';
    window.openModal('modalReplyReview');
};
