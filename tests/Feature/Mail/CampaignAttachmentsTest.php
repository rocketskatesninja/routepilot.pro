<?php

declare(strict_types=1);

use App\Jobs\SendCampaignEmail;
use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DataRetention;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Files sent with Email people: up to 5, 8 MB each, 10 MB together, common types checked
 * by their content; kept privately with the campaign, attached to each email, and cleared
 * after 90 days (the names stay).
 */

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    Customer::factory()->for($this->tenant)->create(['email' => 'r@x.test']);
});

function sendWith(array $files)
{
    return test()->actingAs(test()->admin)->post('/people/email', [
        'audience' => 'customers', 'subject' => 'Price list', 'body' => '<p>Attached.</p>', 'attachments' => $files,
    ]);
}

test('files are kept privately with the campaign and go to each recipient', function () {
    sendWith([
        UploadedFile::fake()->create('Spring Price List!.pdf', 300, 'application/pdf'),
        UploadedFile::fake()->image('pool photo.jpg'),
    ])->assertSessionHasNoErrors();

    $campaign = MailCampaign::query()->latest('id')->firstOrFail();
    expect(array_column($campaign->attachments, 'name'))->toBe(['spring-price-list.pdf', 'pool-photo.jpg']);
    foreach ($campaign->attachments as $file) {
        expect($file['path'])->toStartWith("campaigns/{$this->tenant->id}/{$campaign->id}/");
        Storage::disk('local')->assertExists($file['path']);
    }
    Queue::assertPushed(SendCampaignEmail::class, fn (SendCampaignEmail $job) => count($job->attachments) === 2);

    $mail = new CampaignMail('Price list', '<p>Attached.</p>', 'R', null, null, $this->tenant, null, 'R', $campaign->attachments);
    expect(array_map(fn ($a) => $a->as, $mail->attachments()))->toBe(['spring-price-list.pdf', 'pool-photo.jpg']);
});

test('a file that isn\'t what its name says is refused, and so are other types', function () {
    // A real file (the fakes report their type from the name): PHP code wearing a .pdf name.
    $tmp = tempnam(sys_get_temp_dir(), 'rp');
    file_put_contents($tmp, '<?php echo "hi";');
    sendWith([new UploadedFile($tmp, 'invoice.pdf', null, null, true)])->assertInvalid('attachments.0');
    sendWith([UploadedFile::fake()->create('setup.exe', 10)])->assertInvalid('attachments.0');

    expect(MailCampaign::query()->count())->toBe(0);
});

test('up to 5 files, 8 MB each and 10 MB together', function () {
    sendWith(array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6)))
        ->assertInvalid(['attachments' => 'Attach up to 5 files.']);
    sendWith([UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf')])
        ->assertInvalid(['attachments.0' => 'Each file can be up to 8 MB.']);
    sendWith([UploadedFile::fake()->create('a.pdf', 6000, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 6000, 'application/pdf')])
        ->assertInvalid('attachments');
});

test('Recent shows the files an email carried', function () {
    sendWith([UploadedFile::fake()->create('menu.pdf', 20, 'application/pdf')]);

    $this->get('/people')->assertInertia(fn (Assert $page) => $page->where('recent.0.files', ['menu.pdf']));
});

test('after 90 days the files are cleared and the names stay', function () {
    sendWith([UploadedFile::fake()->create('old.pdf', 20, 'application/pdf')]);
    $old = MailCampaign::query()->latest('id')->firstOrFail();
    $old->forceFill(['sent_at' => now()->subDays(91)])->save();
    $oldPath = $old->attachments[0]['path'];

    sendWith([UploadedFile::fake()->create('new.pdf', 20, 'application/pdf')]);
    $new = MailCampaign::query()->latest('id')->firstOrFail();

    expect(app(DataRetention::class)->purge()['attachments_cleared'])->toBe(1);

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($new->fresh()->attachments[0]['path']);
    expect($old->fresh()->attachments)->toBe([['name' => 'old.pdf', 'size' => 20480, 'mime' => 'application/pdf', 'path' => null]]);
});
