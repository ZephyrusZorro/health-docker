<?php

namespace Database\Seeders;

use App\Models\Audit\Announcement;
use App\Models\Audit\ContentPage;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $pages = [
            [
                'slug'             => 'about',
                'title'            => 'About Nishat Nursing Home',
                'meta_description' => 'Modern, patient-centred care for the community.',
                'content'          => "Nishat Nursing Home has served the community for over two decades, providing compassionate, patient-centred care across a wide range of specialties. Our team of experienced doctors, nurses, and support staff is committed to delivering the highest standard of medical care in a comfortable, modern environment.",
            ],
            [
                'slug'             => 'services',
                'title'            => 'Our Services',
                'meta_description' => 'Comprehensive outpatient and inpatient services.',
                'content'          => "We offer a full spectrum of medical services including general medicine, cardiology, orthopedics, pediatrics, gynecology, ENT, dermatology, radiology, an in-house laboratory, and a round-the-clock emergency unit.",
            ],
            [
                'slug'             => 'visitor-info',
                'title'            => 'Visitor Information',
                'meta_description' => 'Hours, rules, and what to expect during your visit.',
                'content'          => "Visiting hours for general wards: 10am – 12pm and 5pm – 7pm daily. ICU and private rooms have flexible visiting arrangements — please coordinate with the nursing station. We kindly ask all visitors to use the hand sanitisers provided and follow the posted guidelines.",
            ],
            [
                'slug'             => 'contact',
                'title'            => 'Contact & Directions',
                'meta_description' => 'Location, phone, email.',
                'content'          => "Address: 123 Main Road, Hyderabad, Telangana 500001.\nPhone: +91 98000 00000\nEmail: info@nishatnursinghome.com\nEmergency: +91 98000 11111 (24/7)",
            ],
        ];

        foreach ($pages as $p) {
            ContentPage::updateOrCreate(
                ['facility_id' => $facility->id, 'slug' => $p['slug']],
                array_merge($p, [
                    'facility_id'  => $facility->id,
                    'status'       => 'published',
                    'published_at' => now()->subDays(30),
                ]),
            );
        }

        $announcements = [
            [
                'title'        => 'Free health check-up camp',
                'summary'      => 'Diabetes screening camp every first Saturday',
                'content'      => 'Join our monthly diabetes screening camp every first Saturday. Free fasting blood sugar + HbA1c for registered patients.',
                'type'         => 'health_tip',
                'published_at' => now()->subDays(5),
            ],
            [
                'title'        => 'New cardiology consultant',
                'summary'      => 'Dr. Arun Kumar joins the cardiology team',
                'content'      => 'Dr. Arun Kumar, MD (Cardiology), now consulting Mon/Wed/Fri. Book online or at reception.',
                'type'         => 'news',
                'published_at' => now()->subDays(12),
            ],
            [
                'title'        => 'Flu vaccination drive',
                'summary'      => 'Seasonal flu vaccines available at the pharmacy',
                'content'      => 'Seasonal influenza vaccines available at the pharmacy. Walk-in welcome.',
                'type'         => 'announcement',
                'published_at' => now()->subDays(2),
            ],
        ];

        foreach ($announcements as $a) {
            Announcement::updateOrCreate(
                ['facility_id' => $facility->id, 'title' => $a['title']],
                array_merge($a, [
                    'facility_id' => $facility->id,
                    'status'      => 'published',
                    'expires_at'  => now()->addDays(60),
                ]),
            );
        }
    }
}
