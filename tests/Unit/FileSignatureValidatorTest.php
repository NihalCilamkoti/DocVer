<?php

namespace Tests\Unit;

use App\Services\Documents\Security\FileSignatureValidator;
use PHPUnit\Framework\TestCase;

class FileSignatureValidatorTest extends TestCase
{
    protected FileSignatureValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FileSignatureValidator();
    }

    public function test_detects_valid_pdf_signature(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf_test');
        file_put_contents($tmp, "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        $result = $this->validator->validate($tmp, 'pdf');
        unlink($tmp);

        $this->assertTrue($result->isValid);
        $this->assertEquals('pdf', $result->detectedFormat);
        $this->assertEquals('application/pdf', $result->mimeType);
    }

    public function test_detects_valid_png_signature(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'png_test');
        file_put_contents($tmp, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00");

        $result = $this->validator->validate($tmp, 'png');
        unlink($tmp);

        $this->assertTrue($result->isValid);
        $this->assertEquals('png', $result->detectedFormat);
    }

    public function test_blocks_executable_masquerading_as_pdf(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exe_test');
        // Windows PE executable header "MZ"
        file_put_contents($tmp, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00");

        $result = $this->validator->validate($tmp, 'pdf');
        unlink($tmp);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Binary executable format detected', $result->errorMessage);
    }

    public function test_blocks_linux_elf_executable(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'elf_test');
        // Linux ELF header
        file_put_contents($tmp, "\x7FELF\x02\x01\x01\x00");

        $result = $this->validator->validate($tmp, 'docx');
        unlink($tmp);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Binary executable format detected', $result->errorMessage);
    }

    public function test_validates_plain_text_and_csv(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tmp, "id,clause_title,amount\n1,Termination,50000\n2,Confidentiality,0");

        $result = $this->validator->validate($tmp, 'csv');
        unlink($tmp);

        $this->assertTrue($result->isValid);
        $this->assertEquals('csv', $result->detectedFormat);
    }
}
