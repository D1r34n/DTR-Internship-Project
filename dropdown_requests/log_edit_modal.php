<!-- LOG EDIT REQUEST MODAL -->
<div id="logEditModalOverlay">
    <div class="log-edit-modal-box">

        <!-- Modal Header -->
        <div class="log-edit-modal-header">
            <h5 class="log-edit-modal-title">Request Log Edit</h5>
            <button onclick="closeLogEditModal()" class="log-edit-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Placeholder -->
        <p class="log-edit-coming-soon">Log edit request form coming soon.</p>

    </div>
</div>

<script>
    function openLogEditModal() {
        document.getElementById('logEditModalOverlay').style.display = 'flex';
    }

    function closeLogEditModal() {
        document.getElementById('logEditModalOverlay').style.display = 'none';
    }
</script>