<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SDSVG Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISvC0ZrO+5sVnfJZ60lso2O4ppS3mWZikI9hYG6kJt0p6BXHCKb2M" crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h1 class="text-primary mb-0">SDSVG Book</h1>
                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <form id="uploadForm" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                            <input type="file" id="excelFile" name="excelFile" accept=".xls,.xlsx" class="d-none">
                            <button type="button" class="btn btn-primary" id="uploadButton">Upload Excel File</button>
                            <span id="selectedFile" class="text-muted small"></span>
                        </form>
                        <a class="btn btn-outline-secondary" href="#" role="button">Download in PDF format</a>
                        <a class="btn btn-outline-secondary" href="#" role="button">Sample Excel File</a>
                    </div>
                </div>
                <p class="mt-4 text-secondary">Select an Excel file to start working with SDSVG Book.</p>
            </div>
        </div>
    </div>
    <script>
        const uploadButton = document.getElementById('uploadButton');
        const fileInput = document.getElementById('excelFile');
        const selectedFile = document.getElementById('selectedFile');

        uploadButton.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                selectedFile.textContent = fileInput.files[0].name;
            } else {
                selectedFile.textContent = '';
            }
        });
    </script>
</body>
</html>
