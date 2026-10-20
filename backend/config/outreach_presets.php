<?php

/*
 | DIQ-1203: ready-made outreach sequences, by audience and language.
 | Placeholders: {{name}} {{contact}} {{locality}} {{listing}} {{link}}
 | {{nearby_line}} (a sentence with the real number of live listings in
 | their locality, or nothing when there are none yet).
 |
 | Kept short, specific and honest: no invented numbers or promises.
 */

return [
    'school' => [
        'en' => [
            [
                'subject' => 'Learners in {{locality}} are looking for driving schools like {{name}}',
                'body' => "Hi {{contact}},\n\nEvery week, people in {{locality}} search online for a driving school near them. Most never find {{name}}, because it isn't listed where they look.\n\nDriveQ is a new Pune website where learners compare driving schools by area, fees, timings and reviews, then contact the school directly on WhatsApp or by phone. {{nearby_line}}\n\nWe have prepared a free {{listing}} for {{name}}. It stays hidden until you check the details and switch it on. It takes about two minutes:\n{{link}}\n\nNo fee for the basic listing and no commission on your students.\n\nRegards,\nDriveQ team, Pune",
            ],
            [
                'subject' => 'Your free listing for {{name}} is waiting',
                'body' => "Hi {{contact}},\n\nA quick follow-up: your free {{listing}} on DriveQ is ready. Once it is on, you get:\n• enquiries from learners near you, on WhatsApp or by phone\n• an alert the moment someone enquires\n• reviews only from real learners, so good teaching shows\n\nSwitch it on here: {{link}}\n\nRegards,\nDriveQ team",
                'delayDays' => 4,
            ],
            [
                'subject' => 'Should we remove the listing for {{name}}?',
                'body' => "Hi {{contact}},\n\nI haven't heard back, so perhaps now isn't the right time. If you would like learners in {{locality}} to find {{name}}, the free {{listing}} is still here: {{link}}\n\nIf not, use the link at the bottom and we will not write again.\n\nRegards,\nDriveQ team",
                'delayDays' => 7,
            ],
        ],
        'hi' => [
            [
                'subject' => '{{locality}} में लोग {{name}} जैसे ड्राइविंग स्कूल ढूंढ रहे हैं',
                'body' => "नमस्ते {{contact}},\n\n{{locality}} में हर हफ्ते लोग अपने पास का ड्राइविंग स्कूल ऑनलाइन ढूंढते हैं। ज़्यादातर लोगों को {{name}} नहीं मिलता, क्योंकि वह वहाँ लिस्टेड नहीं है जहाँ वे ढूंढते हैं।\n\nDriveQ पुणे की नई वेबसाइट है, जहाँ सीखने वाले इलाका, फीस, समय और रिव्यू देखकर ड्राइविंग स्कूल की तुलना करते हैं और सीधे WhatsApp या फोन पर संपर्क करते हैं। {{nearby_line}}\n\nहमने {{name}} के लिए मुफ्त {{listing}} तैयार की है। जब तक आप जानकारी जांचकर इसे चालू नहीं करते, यह किसी को नहीं दिखेगी। सिर्फ दो मिनट लगते हैं:\n{{link}}\n\nबेसिक लिस्टिंग की कोई फीस नहीं, और स्टूडेंट्स पर कोई कमीशन नहीं।\n\nधन्यवाद,\nDriveQ टीम, पुणे",
            ],
            [
                'subject' => '{{name}} की मुफ्त लिस्टिंग तैयार है',
                'body' => "नमस्ते {{contact}},\n\nपिछले ईमेल की याद दिला रहे हैं: DriveQ पर आपकी मुफ्त {{listing}} तैयार है। चालू करने पर आपको मिलेगा:\n• पास के सीखने वालों से WhatsApp या फोन पर पूछताछ\n• हर नई पूछताछ का तुरंत अलर्ट\n• सिर्फ असली स्टूडेंट्स के रिव्यू\n\nयहाँ चालू करें: {{link}}\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 4,
            ],
            [
                'subject' => 'क्या हम {{name}} की लिस्टिंग हटा दें?',
                'body' => "नमस्ते {{contact}},\n\nआपका जवाब नहीं आया, तो शायद अभी सही समय नहीं है। अगर आप चाहते हैं कि {{locality}} के लोग {{name}} को ढूंढ सकें, तो मुफ्त {{listing}} अभी भी यहाँ है: {{link}}\n\nअगर नहीं, तो नीचे दिए लिंक से अनसब्सक्राइब करें, हम दोबारा ईमेल नहीं करेंगे।\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 7,
            ],
        ],
        'mr' => [
            [
                'subject' => '{{locality}} मधील लोक {{name}} सारखी ड्रायव्हिंग स्कूल शोधत आहेत',
                'body' => "नमस्कार {{contact}},\n\n{{locality}} मध्ये दर आठवड्याला लोक जवळची ड्रायव्हिंग स्कूल ऑनलाइन शोधतात. पण बहुतेकांना {{name}} सापडत नाही, कारण ती ते जिथे शोधतात तिथे लिस्ट केलेली नाही.\n\nDriveQ ही पुण्यातील नवीन वेबसाइट आहे, जिथे शिकणारे परिसर, फी, वेळा आणि रिव्ह्यू पाहून ड्रायव्हिंग स्कूलची तुलना करतात आणि थेट WhatsApp किंवा फोनवर संपर्क करतात. {{nearby_line}}\n\nआम्ही {{name}} साठी मोफत {{listing}} तयार केली आहे. तुम्ही माहिती तपासून ती सुरू करेपर्यंत ती कोणालाही दिसणार नाही. फक्त दोन मिनिटे लागतात:\n{{link}}\n\nबेसिक लिस्टिंगसाठी कोणतीही फी नाही आणि विद्यार्थ्यांवर कमिशन नाही.\n\nधन्यवाद,\nDriveQ टीम, पुणे",
            ],
            [
                'subject' => '{{name}} ची मोफत लिस्टिंग तयार आहे',
                'body' => "नमस्कार {{contact}},\n\nमागील ईमेलची आठवण: DriveQ वर तुमची मोफत {{listing}} तयार आहे. सुरू केल्यावर तुम्हाला मिळेल:\n• जवळच्या शिकणाऱ्यांकडून WhatsApp किंवा फोनवर चौकशी\n• प्रत्येक नवीन चौकशीचा लगेच अलर्ट\n• फक्त खऱ्या विद्यार्थ्यांचे रिव्ह्यू\n\nइथे सुरू करा: {{link}}\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 4,
            ],
            [
                'subject' => '{{name}} ची लिस्टिंग काढून टाकावी का?',
                'body' => "नमस्कार {{contact}},\n\nतुमचे उत्तर आले नाही, त्यामुळे कदाचित आता योग्य वेळ नाही. {{locality}} मधील लोकांना {{name}} सापडावी असे वाटत असल्यास, मोफत {{listing}} अजूनही इथे आहे: {{link}}\n\nनको असल्यास, खालील लिंकवरून अनसबस्क्राइब करा, आम्ही पुन्हा ईमेल करणार नाही.\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 7,
            ],
        ],
    ],
    'trainer' => [
        'en' => [
            [
                'subject' => 'Learners in {{locality}} want a trainer like you, {{contact}}',
                'body' => "Hi {{contact}},\n\nMany learners in {{locality}} prefer one-to-one lessons with an independent trainer, but they can only find big schools online.\n\nDriveQ is a new Pune website where learners find trainers near them, filter by car type, timings, language and women trainers, and contact you directly on WhatsApp or by phone. {{nearby_line}}\n\nWe have prepared a free {{listing}} for you. It stays hidden until you check it and switch it on (about two minutes):\n{{link}}\n\nNo fee for the basic profile and no commission on your students.\n\nRegards,\nDriveQ team, Pune",
            ],
            [
                'subject' => 'Your free trainer profile is ready',
                'body' => "Hi {{contact}},\n\nA quick follow-up: your free {{listing}} on DriveQ is ready. Learners near you can then:\n• find you by area, car type and timings\n• message you on WhatsApp in one tap\n• read reviews from learners you actually trained\n\nSwitch it on here: {{link}}\n\nRegards,\nDriveQ team",
                'delayDays' => 4,
            ],
            [
                'subject' => 'Last note about your DriveQ profile',
                'body' => "Hi {{contact}},\n\nIf you would like more learners from {{locality}}, your free {{listing}} is still waiting: {{link}}\n\nIf not, use the link at the bottom and we will not write again.\n\nRegards,\nDriveQ team",
                'delayDays' => 7,
            ],
        ],
        'hi' => [
            [
                'subject' => '{{locality}} के लोग आप जैसे ट्रेनर ढूंढ रहे हैं, {{contact}}',
                'body' => "नमस्ते {{contact}},\n\n{{locality}} में कई लोग स्वतंत्र ट्रेनर से एक-एक करके सीखना पसंद करते हैं, लेकिन ऑनलाइन उन्हें सिर्फ बड़े स्कूल मिलते हैं।\n\nDriveQ पुणे की नई वेबसाइट है, जहाँ सीखने वाले पास के ट्रेनर ढूंढते हैं, कार का प्रकार, समय, भाषा और महिला ट्रेनर के हिसाब से फ़िल्टर करते हैं, और सीधे आपसे WhatsApp या फोन पर संपर्क करते हैं। {{nearby_line}}\n\nहमने आपके लिए मुफ्त {{listing}} तैयार की है। जब तक आप इसे चालू नहीं करते, यह किसी को नहीं दिखेगी (लगभग दो मिनट):\n{{link}}\n\nबेसिक प्रोफाइल की कोई फीस नहीं, और कोई कमीशन नहीं।\n\nधन्यवाद,\nDriveQ टीम, पुणे",
            ],
            [
                'subject' => 'आपकी मुफ्त ट्रेनर प्रोफाइल तैयार है',
                'body' => "नमस्ते {{contact}},\n\nयाद दिला रहे हैं: DriveQ पर आपकी मुफ्त {{listing}} तैयार है। इसके बाद पास के सीखने वाले:\n• इलाके, कार और समय के हिसाब से आपको ढूंढ सकेंगे\n• एक टैप में WhatsApp पर मैसेज कर सकेंगे\n• आपके सिखाए स्टूडेंट्स के रिव्यू पढ़ सकेंगे\n\nयहाँ चालू करें: {{link}}\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 4,
            ],
            [
                'subject' => 'आपकी DriveQ प्रोफाइल के बारे में आखिरी संदेश',
                'body' => "नमस्ते {{contact}},\n\nअगर आप {{locality}} से और स्टूडेंट्स चाहते हैं, तो आपकी मुफ्त {{listing}} अभी भी यहाँ है: {{link}}\n\nअगर नहीं, तो नीचे दिए लिंक से अनसब्सक्राइब करें, हम दोबारा ईमेल नहीं करेंगे।\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 7,
            ],
        ],
        'mr' => [
            [
                'subject' => '{{locality}} मधील लोक तुमच्यासारखा ट्रेनर शोधत आहेत, {{contact}}',
                'body' => "नमस्कार {{contact}},\n\n{{locality}} मधील अनेकांना स्वतंत्र ट्रेनरकडून वैयक्तिक शिकायला आवडते, पण ऑनलाइन त्यांना फक्त मोठ्या स्कूल सापडतात.\n\nDriveQ ही पुण्यातील नवीन वेबसाइट आहे, जिथे शिकणारे जवळचे ट्रेनर शोधतात, कारचा प्रकार, वेळा, भाषा आणि महिला ट्रेनरनुसार फिल्टर करतात आणि थेट तुमच्याशी WhatsApp किंवा फोनवर संपर्क करतात. {{nearby_line}}\n\nआम्ही तुमच्यासाठी मोफत {{listing}} तयार केली आहे. तुम्ही ती सुरू करेपर्यंत ती कोणालाही दिसणार नाही (सुमारे दोन मिनिटे):\n{{link}}\n\nबेसिक प्रोफाइलसाठी कोणतीही फी नाही आणि कमिशन नाही.\n\nधन्यवाद,\nDriveQ टीम, पुणे",
            ],
            [
                'subject' => 'तुमची मोफत ट्रेनर प्रोफाइल तयार आहे',
                'body' => "नमस्कार {{contact}},\n\nआठवण: DriveQ वर तुमची मोफत {{listing}} तयार आहे. त्यानंतर जवळचे शिकणारे:\n• परिसर, कार आणि वेळेनुसार तुम्हाला शोधू शकतील\n• एका टॅपमध्ये WhatsApp वर मेसेज करू शकतील\n• तुम्ही शिकवलेल्या विद्यार्थ्यांचे रिव्ह्यू वाचू शकतील\n\nइथे सुरू करा: {{link}}\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 4,
            ],
            [
                'subject' => 'तुमच्या DriveQ प्रोफाइलबद्दल शेवटचा संदेश',
                'body' => "नमस्कार {{contact}},\n\n{{locality}} मधून आणखी विद्यार्थी हवे असल्यास, तुमची मोफत {{listing}} अजूनही इथे आहे: {{link}}\n\nनको असल्यास, खालील लिंकवरून अनसबस्क्राइब करा, आम्ही पुन्हा ईमेल करणार नाही.\n\nधन्यवाद,\nDriveQ टीम",
                'delayDays' => 7,
            ],
        ],
    ],
];
