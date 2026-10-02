<?php

/*
 | DIQ-1203: words the outreach email layout fills in by itself, per
 | campaign language. The campaign's own subject and body come from the
 | admin (or a preset in config/outreach_presets.php).
 |
 | Hindi and Marathi were written for this pilot; have a native speaker
 | review them before large sends.
 */

return [
    'en' => [
        'contact_fallback' => 'there',
        'listing' => ['school' => 'school listing', 'trainer' => 'trainer profile'],
        'cta_claim' => 'Claim my free listing',
        'cta_signup' => 'Create my free listing',
        'card_claim' => 'Ready for you · hidden until you switch it on',
        'card_signup' => 'Free · takes about 2 minutes',
        'nearby_one' => '1 driving school in :locality is already on DriveQ.',
        'nearby_many' => ':count driving schools and trainers in :locality are already on DriveQ.',
        'unsubscribe' => 'Not interested? Unsubscribe and we will not write again.',
        'unsubscribe_link' => 'Unsubscribe',
    ],
    'hi' => [
        'contact_fallback' => 'सर/मैडम',
        'listing' => ['school' => 'स्कूल लिस्टिंग', 'trainer' => 'ट्रेनर प्रोफाइल'],
        'cta_claim' => 'मेरी मुफ्त लिस्टिंग चालू करें',
        'cta_signup' => 'मेरी मुफ्त लिस्टिंग बनाएं',
        'card_claim' => 'आपके लिए तैयार · चालू करने तक किसी को नहीं दिखेगी',
        'card_signup' => 'मुफ्त · लगभग 2 मिनट',
        'nearby_one' => ':locality का 1 ड्राइविंग स्कूल पहले से DriveQ पर है।',
        'nearby_many' => ':locality के :count ड्राइविंग स्कूल और ट्रेनर पहले से DriveQ पर हैं।',
        'unsubscribe' => 'रुचि नहीं है? अनसब्सक्राइब करें, हम दोबारा ईमेल नहीं करेंगे।',
        'unsubscribe_link' => 'अनसब्सक्राइब (Unsubscribe)',
    ],
    'mr' => [
        'contact_fallback' => 'सर/मॅडम',
        'listing' => ['school' => 'स्कूल लिस्टिंग', 'trainer' => 'ट्रेनर प्रोफाइल'],
        'cta_claim' => 'माझी मोफत लिस्टिंग सुरू करा',
        'cta_signup' => 'माझी मोफत लिस्टिंग तयार करा',
        'card_claim' => 'तुमच्यासाठी तयार · सुरू करेपर्यंत कोणालाही दिसणार नाही',
        'card_signup' => 'मोफत · सुमारे 2 मिनिटे',
        'nearby_one' => ':locality मधील 1 ड्रायव्हिंग स्कूल आधीच DriveQ वर आहे.',
        'nearby_many' => ':locality मधील :count ड्रायव्हिंग स्कूल आणि ट्रेनर आधीच DriveQ वर आहेत.',
        'unsubscribe' => 'स्वारस्य नाही? अनसबस्क्राइब करा, आम्ही पुन्हा ईमेल करणार नाही.',
        'unsubscribe_link' => 'अनसबस्क्राइब (Unsubscribe)',
    ],
];
