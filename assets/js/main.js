function openModal(id) {
    const e = document.getElementById(id);
    if (e) e.style.display = 'flex';
}

function closeModal(id) {
    const e = document.getElementById(id);
    if (e) e.style.display = 'none';
}

// ================== FUNGSI UTAMA EDIT ==================
function openEdit(data) {
    console.log("🟢 openEdit dipanggil:", data); // DEBUG

    const modal = document.getElementById('m-edit');
    if (!modal) {
        alert("Modal 'm-edit' tidak ditemukan!");
        return;
    }

    modal.style.display = 'flex';

    // Isi data
    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-stok').value = data.stok;
    document.getElementById('edit-min').value = data.stok_minimum;

    // Isi dropdown
    if (document.getElementById('edit-model')) 
        document.getElementById('edit-model').value = data.model;

    if (document.getElementById('edit-warna')) 
        document.getElementById('edit-warna').value = data.warna;

    if (document.getElementById('edit-ukuran')) 
        document.getElementById('edit-ukuran').value = data.ukuran;

    // SKU Display
    const skuDisplay = document.getElementById('edit-sku-display');
    if (skuDisplay) skuDisplay.innerText = data.kode_sku || '-';

    // Judul Modal
    const title = document.getElementById('edit-title');
    if (title) title.textContent = 'Edit Produk — ' + (data.kode_sku || '');
}

// Auto hide flash
const flashMessage = document.querySelector('.flash');
if (flashMessage) {
    setTimeout(() => {
        flashMessage.style.transition = 'opacity .5s';
        flashMessage.style.opacity = '0';
        setTimeout(() => flashMessage.remove(), 500);
    }, 4000);
}

// Close modal saat klik background
window.addEventListener('click', function(event) {
    const modal = document.getElementById('m-edit');
    if (event.target === modal) {
        closeModal('m-edit');
    }
});

// ================== EVENT LISTENER UNTUK TOMBOL EDIT ==================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ main.js berhasil di-load');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('🟢 Tombol Edit diklik');

            openEdit({
                id: this.dataset.id,
                kode_sku: this.dataset.kode,
                stok: parseInt(this.dataset.stok) || 0,
                stok_minimum: parseInt(this.dataset.stokmin) || 30
            });
        });
    });
});