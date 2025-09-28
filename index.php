<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

$error = null;
$tableGroups = [];
$hasUpload = false;
$selectedFileName = '';

function normalize_header_key(string $header): string
{
    return strtolower(trim($header));
}

/**
 * @return mixed|null
 */
function cell_raw_value(array $row, array $headerMap, string $column)
{
    $key = normalize_header_key($column);
    if (!isset($headerMap[$key])) {
        return null;
    }

    return $row[$headerMap[$key]] ?? null;
}

function cell_value(array $row, array $headerMap, string $column): string
{
    $raw = cell_raw_value($row, $headerMap, $column);

    if ($raw === null) {
        return '';
    }

    if (is_string($raw)) {
        return trim($raw);
    }

    if (is_numeric($raw)) {
        if (fmod((float) $raw, 1.0) === 0.0) {
            return (string) intval((string) $raw);
        }

        return rtrim(rtrim(sprintf('%.8F', (float) $raw), '0'), '.');
    }

    return '';
}

function row_has_content(array $row, array $headerMap): bool
{
    foreach ($headerMap as $index) {
        if (!array_key_exists($index, $row)) {
            continue;
        }

        $value = $row[$index];
        if (is_string($value) && trim($value) !== '') {
            return true;
        }
        if (is_numeric($value)) {
            return true;
        }
    }

    return false;
}

function build_member_name(string ...$parts): string
{
    $filtered = array_filter(array_map(static fn(string $part): string => trim($part), $parts), static fn(string $part): bool => $part !== '');

    return implode(' ', $filtered);
}

function format_dob($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_numeric($value)) {
        $days = (float) $value;
        if ($days > 0) {
            $timestamp = (int) round(($days - 25569) * 86400);
            if ($timestamp >= 0) {
                return gmdate('j-M-Y', $timestamp);
            }
        }
    }

    if (is_string($value)) {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'd.m.Y', 'd M Y', 'j M Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $clean);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('j-M-Y');
            }
        }

        $timestamp = strtotime($clean);
        if ($timestamp !== false) {
            return date('j-M-Y', $timestamp);
        }

        return $clean;
    }

    return '';
}

function display_value(?string $value): string
{
    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return '&mdash;';
    }

    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}

function display_multiline(?string $value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '&mdash;';
    }

    $normalized = preg_replace("/(\r\n|\r|\n){2,}/", "\n", $normalized);

    return nl2br(htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    $hasUpload = true;
    $selectedFileName = $_FILES['excelFile']['name'] ?? '';

    if (!isset($_FILES['excelFile']['error']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['excelFile']['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'The uploaded file exceeds the maximum allowed size.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = 'No file was uploaded.';
                break;
            default:
                $error = 'Unable to upload the Excel file (error code ' . $errorCode . ').';
        }
    } else {
        $extension = strtolower(pathinfo($selectedFileName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $error = 'Please upload an Excel .xlsx file.';
        } else {
            $parsed = SimpleXLSX::parse($_FILES['excelFile']['tmp_name']);
            if (!$parsed) {
                $error = SimpleXLSX::parseError();
            } else {
                $rows = $parsed->rows();
                if (!$rows || count($rows) <= 1) {
                    $error = 'The provided Excel file does not contain any data rows.';
                } else {
                    $headerRow = array_shift($rows);
                    $headerMap = [];
                    foreach ($headerRow as $index => $headerCell) {
                        if ($headerCell === null) {
                            continue;
                        }
                        $normalized = normalize_header_key((string) $headerCell);
                        if ($normalized === '') {
                            continue;
                        }
                        if (!isset($headerMap[$normalized])) {
                            $headerMap[$normalized] = $index;
                        }
                    }

                    $missing = [];
                    foreach (['last name', 'first name', 'group'] as $requiredColumn) {
                        if (!isset($headerMap[$requiredColumn])) {
                            $missing[] = ucwords($requiredColumn);
                        }
                    }

                    if ($missing) {
                        $error = 'Missing required column(s): ' . implode(', ', $missing) . '.';
                    } else {
                        $groupIndex = [];

                        foreach ($rows as $row) {
                            if (!is_array($row) || !row_has_content($row, $headerMap)) {
                                continue;
                            }

                            $lastName = cell_value($row, $headerMap, 'last name');
                            $title = cell_value($row, $headerMap, 'title');
                            $firstName = cell_value($row, $headerMap, 'first name');
                            $middleName = cell_value($row, $headerMap, 'middle name');
                            $gender = cell_value($row, $headerMap, 'gender');
                            $relationship = cell_value($row, $headerMap, 'relationship');
                            $dobCell = cell_raw_value($row, $headerMap, 'dob');
                            $dobDisplay = format_dob($dobCell ?? cell_value($row, $headerMap, 'dob'));
                            $education = cell_value($row, $headerMap, 'education');
                            $mobile = cell_value($row, $headerMap, 'mobile');
                            $email = cell_value($row, $headerMap, 'email');
                            $addressRaw = cell_value($row, $headerMap, 'address');
                            $addressNormalized = str_replace(["\r\n", "\r"], "\n", $addressRaw);
                            $address = trim(preg_replace("/(\n){2,}/", "\n", $addressNormalized));
                            $groupLabel = cell_value($row, $headerMap, 'group');
                            if ($groupLabel === '') {
                                $groupLabel = 'Ungrouped';
                            }

                            $orderFlag = cell_value($row, $headerMap, 'record');
                            if ($orderFlag === '') {
                                $orderFlag = cell_value($row, $headerMap, 'p');
                            }
                            if ($orderFlag === '' && $relationship !== '') {
                                $relationshipUpper = strtoupper($relationship);
                                if ($relationshipUpper === 'P' || $relationshipUpper === 'PRIMARY') {
                                    $orderFlag = 'P';
                                }
                            }
                            $orderFlag = strtoupper($orderFlag);

                            if (!isset($groupIndex[$groupLabel])) {
                                $groupIndex[$groupLabel] = count($tableGroups);
                                $tableGroups[] = [
                                    'name' => $groupLabel,
                                    'address' => $address,
                                    'rows' => [],
                                ];
                            }

                            $groupPosition = $groupIndex[$groupLabel];
                            if ($tableGroups[$groupPosition]['address'] === '' && $address !== '') {
                                $tableGroups[$groupPosition]['address'] = $address;
                            }

                            $tableGroups[$groupPosition]['rows'][] = [
                                'member_name' => build_member_name($lastName, $title, $firstName, $middleName),
                                'gender' => $gender,
                                'relationship' => $relationship,
                                'dob' => $dobDisplay,
                                'education' => $education,
                                'mobile' => $mobile,
                                'email' => $email,
                                'address' => $address,
                                'order_flag' => $orderFlag,
                                'last_name_sort' => $lastName,
                                'first_name_sort' => $firstName,
                            ];
                        }

                        foreach ($tableGroups as &$group) {
                            if (empty($group['rows'])) {
                                continue;
                            }

                            usort(
                                $group['rows'],
                                static function (array $a, array $b): int {
                                    $priorityA = $a['order_flag'] === 'P' ? 0 : 1;
                                    $priorityB = $b['order_flag'] === 'P' ? 0 : 1;

                                    if ($priorityA !== $priorityB) {
                                        return $priorityA <=> $priorityB;
                                    }

                                    $lastNameComparison = strcasecmp($a['last_name_sort'], $b['last_name_sort']);
                                    if ($lastNameComparison !== 0) {
                                        return $lastNameComparison;
                                    }

                                    return strcasecmp($a['first_name_sort'], $b['first_name_sort']);
                                }
                            );

                            $group['row_count'] = count($group['rows']);
                        }
                        unset($group);

                        if (empty($tableGroups)) {
                            $error = 'No member rows were found in the uploaded Excel file.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SDSVG Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISvC0ZrO+5sVnfJZ60lso2O4ppS3mWZikI9hYG6kJt0p6BXHCKb2M" crossorigin="anonymous">
    <style>
        body {
            background-color: #f3f4f6;
            color: #1f2933;
        }

        .card {
            border: 1px solid #d7dce1;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(40, 49, 66, 0.08);
        }

        .page-title {
            color: #223046;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .action-toolbar .btn {
            min-width: 180px;
        }

        .action-toolbar .btn-primary {
            background-color: #39465a;
            border-color: #39465a;
        }

        .action-toolbar .btn-primary:hover,
        .action-toolbar .btn-primary:focus {
            background-color: #2f3a4a;
            border-color: #2b3544;
        }

        .member-table {
            border: 1px solid #d6dbe1;
            font-size: 0.95rem;
            color: #1f2933;
        }

        .member-table thead th {
            background: #e5e8ed;
            color: #1f2933;
            border: 1px solid #d1d6dc;
            font-weight: 600;
            text-transform: none;
            font-size: 0.92rem;
            padding: 0.65rem 0.75rem;
        }

        .member-table tbody td {
            border: 1px solid #d9dde2;
            vertical-align: top;
            padding: 0.65rem 0.75rem;
        }

        .group-header-row td {
            background: #f0f2f6;
            border: 1px solid #d6dbe1;
            font-weight: 600;
            color: #26354a;
        }

        .srno-cell {
            text-align: center;
            font-weight: 600;
            background: #f9fafc;
            min-width: 55px;
        }

        .address-cell {
            min-width: 220px;
            white-space: pre-line;
        }

        @media (max-width: 576px) {
            .action-toolbar {
                width: 100%;
            }

            .action-toolbar .btn {
                flex: 1 1 auto;
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h1 class="page-title mb-0">SDSVG Book</h1>
                    <div class="action-toolbar d-flex flex-wrap justify-content-md-end gap-2">
                        <form id="uploadForm" method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                            <input type="file" id="excelFile" name="excelFile" accept=".xlsx" class="d-none">
                            <button type="button" class="btn btn-primary" id="uploadButton">Upload Excel File</button>
                            <span id="selectedFile" class="text-muted small"><?= htmlspecialchars($selectedFileName, ENT_QUOTES, 'UTF-8') ?></span>
                        </form>
                        <a class="btn btn-outline-secondary" href="output.pdf" download>Download in PDF format</a>
                        <a class="btn btn-outline-secondary" href="example.xlsx" download>Sample Excel File</a>
                    </div>
                </div>
                <p class="mt-4 text-secondary">Upload the member Excel sheet to view responsive records laid out as in the PDF reference.</p>

                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php elseif ($hasUpload && !empty($tableGroups)): ?>
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered align-middle member-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">No.</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Gender</th>
                                    <th scope="col">Relation</th>
                                    <th scope="col">DOB</th>
                                    <th scope="col">Education</th>
                                    <th scope="col">Mobile</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $groupNumber = 0; ?>
                                <?php foreach ($tableGroups as $group): ?>
                                    <?php if (empty($group['rows'])) {
                                        continue;
                                    }
                                    $groupNumber++;
                                    ?>
                                    <tr class="group-header-row">
                                        <td colspan="9">Group: <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <?php $rowspan = $group['row_count'] ?? count($group['rows']); ?>
                                    <?php foreach ($group['rows'] as $index => $member): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?= (int) $rowspan ?>" class="srno-cell align-middle"><?= $groupNumber ?></td>
                                            <?php endif; ?>
                                            <td><?= display_value($member['member_name']) ?></td>
                                            <td><?= display_value($member['gender']) ?></td>
                                            <td><?= display_value($member['relationship']) ?></td>
                                            <td><?= display_value($member['dob']) ?></td>
                                            <td><?= display_value($member['education']) ?></td>
                                            <td><?= display_value($member['mobile']) ?></td>
                                            <td><?= display_value($member['email']) ?></td>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?= (int) $rowspan ?>" class="address-cell align-middle"><?= display_multiline($group['address']) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($hasUpload): ?>
                    <div class="alert alert-info" role="alert">
                        No member data was available to display from the uploaded file.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const uploadButton = document.getElementById('uploadButton');
        const fileInput = document.getElementById('excelFile');
        const selectedFile = document.getElementById('selectedFile');
        const uploadForm = document.getElementById('uploadForm');

        uploadButton.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                selectedFile.textContent = fileInput.files[0].name;
                uploadForm.submit();
            } else {
                selectedFile.textContent = '';
            }
        });
    </script>
</body>
</html>
