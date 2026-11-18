// assets/js/main.js - Interaktivitas Website LN Digital

// 1. Filter Kategori Template
function filterCategory(category, btnElement) {
    var buttons = document.querySelectorAll('.pill-btn');
    buttons.forEach(function(btn) {
        btn.classList.remove('active');
    });
    if (btnElement) {
        btnElement.classList.add('active');
    }

    var cards = document.querySelectorAll('.template-card');
    cards.forEach(function(card) {
        var cardCategory = card.getAttribute('data-category');
        if (category === 'Semua' || cardCategory === category) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// 2. Modal Pemesanan Template
function openOrderModal(templateId, templateTitle, price) {
    document.getElementById('modalTemplateId').value = templateId;
    document.getElementById('modalTemplateName').value = templateTitle;
    document.getElementById('modalTemplateTitle').innerText = 'Pesan: ' + templateTitle;
    document.getElementById('modalTotalPrice').value = price;
    document.getElementById('modalPriceLabel').innerText = 'Rp ' + Number(price).toLocaleString('id-ID');

    var modal = document.getElementById('orderModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeOrderModal() {
    var modal = document.getElementById('orderModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 3. Modal Pratinjau Desain
function openPreviewModal(title, category, imageUrl, features, priceFormatted) {
    document.getElementById('previewTitle').innerText = title;
    document.getElementById('previewCategory').innerText = category;
    document.getElementById('previewImage').src = imageUrl;
    document.getElementById('previewPrice').innerText = 'Rp ' + priceFormatted;

    var featContainer = document.getElementById('previewFeatures');
    featContainer.innerHTML = '';
    var feats = features.split(',');
    feats.forEach(function(f) {
        var span = document.createElement('span');
        span.className = 'feat-tag';
        span.innerText = '✓ ' + f.trim();
        featContainer.appendChild(span);
    });

    var modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closePreviewModal() {
    var modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Tutup modal jika klik di latar belakang
window.onclick = function(event) {
    var orderModal = document.getElementById('orderModal');
    var previewModal = document.getElementById('previewModal');
    if (event.target === orderModal) {
        orderModal.style.display = 'none';
    }
    if (event.target === previewModal) {
        previewModal.style.display = 'none';
    }
};
