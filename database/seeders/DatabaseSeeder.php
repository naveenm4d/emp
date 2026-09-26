<?php

namespace Database\Seeders;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Enums\DietaryOption;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\SeatAssignment;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data. Every account's password is "password".
     *
     *  Staff:   admin@emp.test (super_admin), viewer@emp.test (viewer)  -> /admin
     *  Client:  client@emp.test                                         -> /app
     */
    public function run(EventDesignServiceInterface $designs): void
    {
        $this->call(TemplateSeeder::class);
        $classic = Template::query()->where('key', 'classic')->value('latest_version_id');
        $modern = Template::query()->where('key', 'modern')->value('latest_version_id');
        $floral = Template::query()->where('key', 'floral')->value('latest_version_id');

        StaffMember::factory()->superAdmin()->create(['name' => 'EMP Admin', 'email' => 'admin@emp.test']);
        StaffMember::factory()->role(StaffRole::Viewer)->create(['name' => 'EMP Viewer', 'email' => 'viewer@emp.test']);

        $client = Client::factory()->create(['name' => 'Demo Client', 'email' => 'client@emp.test']);

        $gala = Event::factory()->for($client)->openForRegistration()->capacity(100)
            ->registrationSettings([
                'contact' => ['email' => 'required', 'company' => 'optional', 'job_title' => 'optional'],
                'attendance' => ['allow_maybe' => true],
                'party' => ['plus_ones' => true, 'max_additional_guests' => 1],
                'dietary' => ['enabled' => true, 'options' => array_column(DietaryOption::cases(), 'value'), 'notes' => true],
                'responses' => ['editable' => true],
            ])
            ->create([
                'title' => 'Summer Gala 2026',
                'slug' => 'summer-gala-2026',
                'event_type' => EventType::Corporate,
                'template_version_id' => $modern,
            ]);
        $this->attachDemoPhoto($gala, [30, 36, 64], [198, 120, 255]);
        RegistrationQuestion::factory()->for($gala)->choice(QuestionType::Select, ['S', 'M', 'L', 'XL'])->required()
            ->create(['label' => 'What is your T-shirt size?', 'sort_order' => 0]);
        RegistrationQuestion::factory()->for($gala)->choice(QuestionType::Radio, ['Yes', 'No'])
            ->create(['label' => 'Would you like transportation?', 'sort_order' => 1]);

        $dinner = Event::factory()->for($client)->requiresApproval()->create([
            'title' => 'Founders Dinner',
            'slug' => 'founders-dinner',
            'template_version_id' => $classic,
        ]);
        $this->attachDemoPhoto($dinner, [214, 190, 150], [120, 90, 60]);

        Event::factory()->for($client)->cancelled()->create([
            'title' => 'Spring Picnic',
            'slug' => 'spring-picnic',
            'template_version_id' => $floral,
        ]);

        $guests = Guest::factory()->count(12)->for($gala)->create();
        Guest::factory()->count(3)->for($gala)->status(ApprovalStatus::Pending)->create(['source' => GuestSource::PublicLink]);
        Guest::factory()->for($gala)->status(ApprovalStatus::Waitlisted)->create();

        foreach ($guests->take(6) as $index => $guest) {
            $rsvp = Rsvp::factory()->for($guest)->for($gala)->sent()->create(
                $index < 2 ? ['status' => RsvpStatus::Accepted, 'responded_at' => now()] : [],
            );

            $guest->update(['rsvp_status' => $index < 2 ? GuestRsvpStatus::Confirmed : GuestRsvpStatus::Pending]);

            Notification::factory()->for($guest)->for($gala)->create([
                'recipient' => $guest->phone,
                'message' => "Hi {$guest->name}, you're invited to {$gala->title}! Please RSVP here: {$rsvp->rsvpUrl()}",
                'status' => 'sent',
                'sent_at' => now(),
                'provider_message_id' => 'mock-wamid-seed-'.$index,
            ]);
        }

        Notification::factory()->for($guests->last())->for($gala)->failed('WhatsApp API error (400): Invalid phone number')->create([
            'recipient' => $guests->last()->phone,
        ]);

        // Seating: two tables, with the confirmed guests at the first.
        $head = EventTable::factory()->for($gala)->seats(8)->create(['name' => 'Table 1', 'sort_order' => 0]);
        EventTable::factory()->for($gala)->seats(6)->create(['name' => 'Table 2', 'sort_order' => 1]);
        foreach ($guests->take(2) as $index => $guest) {
            SeatAssignment::factory()->create([
                'table_id' => $head->id,
                'event_id' => $gala->id,
                'guest_id' => $guest->id,
                'seat_number' => $index + 1,
            ]);
        }

        // A second client so staff lists are not trivially one row.
        Event::factory()->count(2)->published()->create(['template_version_id' => $classic]);

        // Generate every event's invitation so previews and RSVP pages are ready.
        Event::query()->each(fn (Event $event) => $designs->render($event));
    }

    /**
     * Stores a generated gradient "photo" in the event's img_1 slot.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function attachDemoPhoto(Event $event, array $from, array $to): void
    {
        [$width, $height] = [1200, 1500];
        $image = imagecreatetruecolor($width, $height);

        $channel = fn (int $a, int $b, float $t): int => max(0, min(255, (int) round($a + ($b - $a) * $t)));

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate($image, $channel($from[0], $to[0], $t), $channel($from[1], $to[1], $t), $channel($from[2], $to[2], $t));

            if ($color !== false) {
                imageline($image, 0, $y, $width, $y, $color);
            }
        }

        $highlight = imagecolorallocatealpha($image, 255, 255, 255, 110);

        for ($i = 0; $i < 12 && $highlight !== false; $i++) {
            $size = random_int(120, 420);
            imagefilledellipse($image, random_int(0, $width), random_int(0, $height), $size, $size, $highlight);
        }

        ob_start();
        imagejpeg($image, null, 85);
        $contents = (string) ob_get_clean();

        $disk = (string) config('emp.media_disk');
        $path = "events/{$event->storageDirectory()}/media/".Str::uuid().'.jpg';
        Storage::disk($disk)->put($path, $contents);

        $event->media()->create([
            'slot_key' => 'img_1',
            'type' => MediaType::Image,
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'demo-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($contents),
        ]);
    }
}
