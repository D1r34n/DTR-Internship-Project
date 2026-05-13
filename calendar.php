<?php
// Sample data (replace with DB later)
$items = [
    "Human Resources",
    "Finance",
    "Information Technology",
    "Marketing",
    "Operations",
    "Logistics",
    "Customer Support",
    "Research & Development",
    "Legal",
    "Procurement"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Searchable Dropdown</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .dropdown-menu {
            max-height: 250px;
            overflow-y: auto;
        }

        .search-box {
            position: sticky;
            top: 0;
            background: white;
            padding: 8px;
            z-index: 10;
        }
    </style>
</head>
<body class="p-5">

<div class="container">

    <h4 class="mb-3">Searchable Bootstrap Dropdown</h4>

    <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            Select Department
        </button>

        <ul class="dropdown-menu w-100">

            <!-- Search Input -->
            <li class="search-box">
                <input type="text" id="dropdownSearch" class="form-control form-control-sm"
                       placeholder="Search...">
            </li>

            <li><hr class="dropdown-divider"></li>

            <!-- Items -->
            <div id="dropdownItems">
                <?php foreach ($items as $item): ?>
                    <li>
                        <a class="dropdown-item" href="#">
                            <?= htmlspecialchars($item) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </div>

            <!-- No result -->
            <li id="noResult" class="text-center text-muted d-none p-2">
                No results found
            </li>

        </ul>
    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const searchInput = document.getElementById('dropdownSearch');
const items = document.querySelectorAll('#dropdownItems .dropdown-item');
const noResult = document.getElementById('noResult');

searchInput.addEventListener('keyup', function () {
    let filter = this.value.toLowerCase();
    let visibleCount = 0;

    items.forEach(item => {
        let text = item.textContent.toLowerCase();

        if (text.includes(filter)) {
            item.parentElement.style.display = "";
            visibleCount++;
        } else {
            item.parentElement.style.display = "none";
        }
    });

    noResult.classList.toggle('d-none', visibleCount !== 0);
});
</script>

</body>
</html>