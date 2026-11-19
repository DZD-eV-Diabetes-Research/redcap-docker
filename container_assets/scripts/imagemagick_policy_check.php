<?php
/**
 * Check that Imagick + Ghostscript can do what REDCap needs, and nothing more.
 *
 * REDCap uses Imagick only to render PDFs to PNG images (PDF::convertPdfToImages for inline
 * PDFs in PDF exports / eConsent, and the Imagick test in Control Center > Configuration Check).
 * The ImageMagick policy (/etc/ImageMagick-7/policy.xml, set in the Dockerfile) therefore allows
 * PDF read-only and denies the PostScript family, which would otherwise be handed straight to
 * Ghostscript.
 *
 * Runs at image build time (the build fails on a non-zero exit) and from the test suite.
 * Usage: php imagemagick_policy_check.php
 */

declare(strict_types=1);

const POLICY_DENIED = 499; // ImagickException code for "not allowed by the security policy"

// Blank one-page PDF, the same one REDCap's ControlCenter/check.php renders.
$blank_pdf = base64_decode(
    'JVBERi0xLjEKJeLjz9MKMSAwIG9iaiAKPDwKL1BhZ2VzIDIgMCBSCi9UeXBlIC9DYXRhbG9nCj4+CmVuZG9iaiAKMiAwIG9iaiAKPDwK'
    . 'L01lZGlhQm94IFswIDAgNTk1IDg0Ml0KL0tpZHMgWzMgMCBSXQovQ291bnQgMQovVHlwZSAvUGFnZXMKPj4KZW5kb2JqIAozIDAgb2Jq'
    . 'IAo8PAovUGFyZW50IDIgMCBSCi9NZWRpYUJveCBbMCAwIDU5NSA4NDJdCi9UeXBlIC9QYWdlCj4+CmVuZG9iaiB4cmVmCjAgNAowMDAw'
    . 'MDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMTUgMDAwMDAgbiAKMDAwMDAwMDA2NiAwMDAwMCBuIAowMDAwMDAwMTQ5IDAwMDAwIG4gCnRy'
    . 'YWlsZXIKCjw8Ci9Sb290IDEgMCBSCi9TaXplIDQKPj4Kc3RhcnR4cmVmCjIyMQolJUVPRgo='
);
$ps  = "%!PS-Adobe-3.0\n%%BoundingBox: 0 0 10 10\nnewpath 0 0 moveto 10 10 lineto stroke showpage\n";
$eps = "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 10 10\nnewpath 0 0 moveto 10 10 lineto stroke showpage\n";

$dir = sys_get_temp_dir() . '/imagemagick-policy-check-' . getmypid();
mkdir($dir, 0700);
$failed = 0;

function check(string $name, bool $expect_allowed, callable $fn): void
{
    global $failed;
    try {
        $fn();
        $ok = $expect_allowed;
        $detail = 'allowed';
    } catch (ImagickException $e) {
        // A denial only counts if the policy caused it, not some other error.
        $ok = !$expect_allowed && $e->getCode() === POLICY_DENIED;
        $detail = 'code ' . $e->getCode() . ': ' . $e->getMessage();
    } catch (Throwable $e) {
        $ok = false;
        $detail = get_class($e) . ': ' . $e->getMessage();
    }
    if (!$ok) {
        $failed++;
    }
    printf("%s  %-40s %s\n", $ok ? 'ok  ' : 'FAIL', $name, $detail);
}

function write_file(string $path, string $content): string
{
    file_put_contents($path, $content);
    return $path;
}

// What REDCap needs: read a PDF (via Ghostscript) and write PNG.
check('read PDF, render to PNG', true, function () use ($blank_pdf) {
    $im = new Imagick();
    $im->setResolution(200, 200);
    $im->readImageBlob($blank_pdf);
    $im->setImageFormat('png');
    if (strlen($im->getImageBlob()) === 0) {
        throw new RuntimeException('empty PNG');
    }
});

// PDF is read-only.
check('write PDF', false, function () use ($dir) {
    $im = new Imagick();
    $im->newImage(10, 10, 'white');
    $im->writeImage("PDF:$dir/out.pdf");
});

// The PostScript family is denied, whatever the file is called.
foreach (['PS', 'PS2', 'PS3', 'EPS', 'EPI', 'EPSI', 'EPSF', 'EPT', 'XPS'] as $coder) {
    check("read $coder", false, function () use ($coder, $dir, $eps) {
        new Imagick($coder . ':' . write_file("$dir/in." . strtolower($coder), $eps));
    });
}
check('read *.ps (format detected from content)', false, function () use ($dir, $ps) {
    new Imagick(write_file("$dir/detect.ps", $ps));
});
check('read PostScript named *.pdf', false, function () use ($dir, $ps) {
    new Imagick(write_file("$dir/fake.pdf", $ps));
});
check('write PS', false, function () use ($dir) {
    $im = new Imagick();
    $im->newImage(10, 10, 'white');
    $im->writeImage("PS:$dir/out.ps");
});

array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);

if ($failed > 0) {
    fwrite(STDERR, "ImageMagick policy check: $failed check(s) failed\n");
    exit(1);
}
echo "ImageMagick policy check: all checks passed\n";
