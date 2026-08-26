<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\httpclient;

require_once(__DIR__ . '/../fixtures/test_upload_client.php');

/**
 * Tests for the Datacurso API HTTP client file upload.
 *
 * The upload contract is shared with local_coursegen, which holds Moodle
 * stored_file objects and has no path on disk to give. That contract was lost
 * once in a branch merge, so these tests pin both the signature and the
 * behaviour that depends on it.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base::upload_file
 */
final class datacurso_api_base_test extends \advanced_testcase {
    /**
     * Build a real stored_file in the draft area, as a caller would hold.
     *
     * @param string $content File content.
     * @param string $filename File name.
     * @param string $mimetype MIME type to record on the file.
     * @return \stored_file
     */
    private function make_stored_file(
        string $content = 'syllabus content',
        string $filename = 'syllabus.pdf',
        string $mimetype = 'application/pdf'
    ): \stored_file {
        $record = [
            'contextid' => \context_system::instance()->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];

        return get_file_storage()->create_file_from_string($record, $content);
    }

    /**
     * The second parameter must be a stored_file, which is what callers hold.
     *
     * Declaring it as a string path is what broke syllabus upload: Moodle keeps
     * files in the file storage API, so no caller has a path to pass.
     */
    public function test_upload_file_takes_a_stored_file(): void {
        $param = (new \ReflectionMethod(datacurso_api_base::class, 'upload_file'))->getParameters()[1];

        $this->assertSame('stored_file', $param->getType()->getName());
    }

    /**
     * Extra POST parameters are the third argument, not the fifth.
     *
     * Callers pass three positional arguments. Inserting parameters ahead of
     * this one silently lands the extras in the wrong slot.
     */
    public function test_extra_params_are_the_third_parameter(): void {
        $params = (new \ReflectionMethod(datacurso_api_base::class, 'upload_file'))->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('extraparams', $params[2]->getName());
        $this->assertSame('array', $params[2]->getType()->getName());
    }

    /**
     * A stored_file is uploaded with its own name and MIME type, plus the extras.
     */
    public function test_upload_file_sends_the_file_with_its_name_and_mimetype(): void {
        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $file = $this->make_stored_file('report body', 'informe.pdf', 'application/pdf');

        $client->upload_file('/course/sillabus/upload', $file, ['thread_id' => 'abc123']);

        $this->assertSame('UPLOAD', $client->method);
        $this->assertSame('/course/sillabus/upload', $client->path);
        $this->assertSame('abc123', $client->payload['thread_id']);

        $curlfile = $client->payload['file'];
        $this->assertInstanceOf(\CURLFile::class, $curlfile);
        $this->assertSame('informe.pdf', $curlfile->getPostFilename());
        $this->assertSame('application/pdf', $curlfile->getMimeType());
    }

    /**
     * The temporary copy holds the file content while the request is in flight.
     */
    public function test_the_temporary_copy_holds_the_file_content(): void {
        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $file = $this->make_stored_file('exact bytes to upload');

        $client->upload_file('/course/sillabus/upload', $file);

        $this->assertSame('exact bytes to upload', $client->contentatrequest);
    }

    /**
     * The temporary copy is removed once the request completes.
     */
    public function test_the_temporary_copy_is_removed_afterwards(): void {
        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');

        $client->upload_file('/course/sillabus/upload', $this->make_stored_file());

        $this->assertFileDoesNotExist($client->payload['file']->getFilename());
    }

    /**
     * The temporary copy is removed even when the request throws.
     *
     * Without the finally block a failed upload leaves the file behind, and a
     * syllabus is not small.
     */
    public function test_the_temporary_copy_is_removed_when_the_request_fails(): void {
        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $client->failwith = new \moodle_exception('error');

        try {
            $client->upload_file('/course/sillabus/upload', $this->make_stored_file());
            $this->fail('Expected the request to throw.');
        } catch (\moodle_exception $e) {
            $this->assertFileDoesNotExist($client->temppath);
        }
    }
}
