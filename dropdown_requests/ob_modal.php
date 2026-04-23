<!-- OB REQUEST MODAL -->
<div id="obModalOverlay">
    <div class="ob-modal-box">

        <!-- Modal Header -->
        <div class="ob-modal-header">
            <h5 class="ob-modal-title">File OB Request</h5>
            <button onclick="closeOBModal()" class="ob-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Placeholder -->
        <p class="ob-coming-soon">OB request form coming soon.</p>

    </div>
</div>

<script>
    function openOBModal() {
        document.getElementById('obModalOverlay').style.display = 'flex';
    }

    function closeOBModal() {
        document.getElementById('obModalOverlay').style.display = 'none';
    }
</script>