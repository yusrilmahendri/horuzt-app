<?php

return [
    'supported' => [
        'islam' => 'Islam',
        'christian' => 'Kristen',
        'catholic' => 'Katolik',
        'hindu' => 'Hindu',
        'buddhist' => 'Buddha',
        'confucian' => 'Konghucu',
        'universal' => 'Universal',
        'custom' => 'Custom',
    ],

    'aliases' => [
        'moslem' => 'islam',
        'muslim' => 'islam',
        'kristen' => 'christian',
        'protestan' => 'christian',
        'katolik' => 'catholic',
        'buddha' => 'buddhist',
        'konghucu' => 'confucian',
        'khonghucu' => 'confucian',
        'umum' => 'universal',
        'general' => 'universal',
        'lainnya' => 'custom',
        'lain' => 'custom',
    ],

    'fields' => [
        'opening_greeting',
        'closing_greeting',
        'invitation_intro',
        'whatsapp_message',
        'quote_text',
        'quote_source',
        'prayer_text',
        'blessing_text',
    ],

    'templates' => [
        'universal' => [
            'opening_greeting' => 'Dengan penuh rasa syukur, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dalam acara pernikahan kami.',
            'closing_greeting' => 'Merupakan kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.',
            'invitation_intro' => 'Kami mengundang Anda untuk menjadi bagian dari hari bahagia kami.',
            'whatsapp_message' => "Halo {{guest_name}},\n\nDengan bahagia kami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nTerima kasih atas doa dan kehadirannya.",
            'quote_text' => 'Cinta yang tulus adalah awal dari perjalanan yang indah.',
            'quote_source' => null,
            'prayer_text' => 'Semoga acara ini berjalan lancar dan menjadi awal kehidupan yang penuh kebahagiaan.',
            'blessing_text' => 'Doa restu Anda sangat berarti bagi kami.',
        ],

        'islam' => [
            'opening_greeting' => 'Assalamu\'alaikum Warahmatullahi Wabarakatuh.',
            'closing_greeting' => 'Wassalamu\'alaikum Warahmatullahi Wabarakatuh.',
            'invitation_intro' => 'Dengan memohon rahmat dan ridho Allah SWT, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami.',
            'whatsapp_message' => "Assalamu'alaikum {{guest_name}},\n\nDengan memohon rahmat Allah SWT, kami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.",
            'quote_text' => 'Dan di antara tanda-tanda kekuasaan-Nya ialah Dia menciptakan untukmu pasangan hidup dari jenismu sendiri.',
            'quote_source' => 'QS. Ar-Rum: 21',
            'prayer_text' => 'Semoga Allah SWT memberkahi pernikahan ini dan menghimpun keduanya dalam kebaikan.',
            'blessing_text' => 'Doa restu Bapak/Ibu/Saudara/i sangat berarti bagi kami.',
        ],

        'christian' => [
            'opening_greeting' => 'Salam sejahtera dalam kasih Tuhan.',
            'closing_greeting' => 'Tuhan memberkati.',
            'invitation_intro' => 'Dengan penuh syukur kepada Tuhan, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pemberkatan pernikahan kami.',
            'whatsapp_message' => "Salam sejahtera {{guest_name}},\n\nDengan sukacita kami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nTuhan memberkati.",
            'quote_text' => 'Demikianlah mereka bukan lagi dua, melainkan satu.',
            'quote_source' => 'Matius 19:6',
            'prayer_text' => 'Kiranya Tuhan menyertai dan memberkati perjalanan rumah tangga kami.',
            'blessing_text' => 'Kehadiran dan doa Bapak/Ibu/Saudara/i menjadi berkat bagi kami.',
        ],

        'catholic' => [
            'opening_greeting' => 'Salam damai dalam kasih Kristus.',
            'closing_greeting' => 'Berkah Dalem.',
            'invitation_intro' => 'Dengan ungkapan syukur kepada Tuhan, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri perayaan sakramen perkawinan kami.',
            'whatsapp_message' => "Salam damai {{guest_name}},\n\nDengan sukacita kami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nBerkah Dalem.",
            'quote_text' => 'Apa yang telah dipersatukan Allah, tidak boleh diceraikan manusia.',
            'quote_source' => 'Markus 10:9',
            'prayer_text' => 'Semoga Tuhan memberkati janji suci dan perjalanan keluarga kami.',
            'blessing_text' => 'Doa dan restu Bapak/Ibu/Saudara/i adalah sukacita bagi kami.',
        ],

        'hindu' => [
            'opening_greeting' => 'Om Swastyastu.',
            'closing_greeting' => 'Om Shanti Shanti Shanti Om.',
            'invitation_intro' => 'Atas asung kertha wara nugraha Ida Sang Hyang Widhi Wasa, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami.',
            'whatsapp_message' => "Om Swastyastu {{guest_name}},\n\nKami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nOm Shanti Shanti Shanti Om.",
            'quote_text' => 'Semoga dharma menjadi dasar perjalanan rumah tangga yang harmonis.',
            'quote_source' => null,
            'prayer_text' => 'Semoga Ida Sang Hyang Widhi Wasa memberikan kelancaran dan kebahagiaan.',
            'blessing_text' => 'Doa restu Bapak/Ibu/Saudara/i sangat kami harapkan.',
        ],

        'buddhist' => [
            'opening_greeting' => 'Namo Buddhaya.',
            'closing_greeting' => 'Sabbe Satta Bhavantu Sukhitatta.',
            'invitation_intro' => 'Dengan penuh kebahagiaan, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami.',
            'whatsapp_message' => "Namo Buddhaya {{guest_name}},\n\nKami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nSabbe Satta Bhavantu Sukhitatta.",
            'quote_text' => 'Kebahagiaan bertambah ketika dibagikan dengan kasih dan ketulusan.',
            'quote_source' => null,
            'prayer_text' => 'Semoga semua makhluk hidup berbahagia dan acara ini berjalan penuh kedamaian.',
            'blessing_text' => 'Kehadiran dan doa baik Anda sangat berarti bagi kami.',
        ],

        'confucian' => [
            'opening_greeting' => 'Salam kebajikan.',
            'closing_greeting' => 'Wei De Dong Tian.',
            'invitation_intro' => 'Dengan penuh rasa syukur kepada Tian, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami.',
            'whatsapp_message' => "Salam kebajikan {{guest_name}},\n\nKami mengundang Anda untuk hadir di pernikahan {{bride_name}} dan {{groom_name}} pada {{event_date}} di {{event_location}}.\n\nDetail undangan: {{invitation_url}}\n\nWei De Dong Tian.",
            'quote_text' => 'Keluarga harmonis tumbuh dari ketulusan, hormat, dan kebajikan.',
            'quote_source' => null,
            'prayer_text' => 'Semoga Tian memberkahi perjalanan keluarga kami dengan kebajikan dan keharmonisan.',
            'blessing_text' => 'Doa restu dan kehadiran Bapak/Ibu/Saudara/i sangat kami harapkan.',
        ],

        'custom' => [],
    ],
];
