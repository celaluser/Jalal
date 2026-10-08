<?php

return [
    'error_not_configured' => 'AI is not set up yet. Ask the platform administrator to add an AI key.',
    'error_no_credits' => 'You have used all AI credits of your plan for this month. They renew on the 1st, or you can upgrade your plan.',
    'error_provider_error' => 'The AI service did not answer. Please try again in a moment.',
    'error_rate_limited' => 'The AI service is busy right now. Please try again in a minute.',
    'error_bad_response' => 'The AI could not produce a usable answer this time. Please try again, or add more detail.',
    'error_limit_categories' => 'Importing this menu would go over the category limit of your plan.',
    'error_limit_products' => 'Importing this menu would go over the product limit of your plan.',

    'write' => 'Write with AI', 'writing' => 'Writing…', 'hints' => 'Ingredients or notes (optional)', 'tone' => 'Style',
    'tone_appetizing' => 'Appetizing', 'tone_short' => 'Short and plain', 'tone_elegant' => 'Elegant', 'tone_playful' => 'Playful',
    'translate' => 'Translate with AI', 'translating' => 'Translating…', 'translate_hint' => 'Fills the empty fields of the other languages from your main language.',
    'suggest_tags' => 'Suggest allergens with AI', 'suggesting' => 'Checking…',
    'tags_notice' => 'Suggested by AI. Please check every allergen yourself: guests rely on this information.', 'tags_none' => 'No allergens or diet labels were suggested.',
    'tags_applied' => 'Ticked: :list',
    'credits_left' => ':count credits left this month', 'credits_unlimited' => 'AI credits are not limited on your plan', 'credits_cost' => 'Costs :count credit(s)',
    'review_note' => 'Check the text before saving. AI can make mistakes.',

    'import_title' => 'Import a menu with AI', 'import_sub' => 'Paste your menu from a PDF, a website or a document. AI sorts it into categories and dishes, and you review everything before it is added.',
    'import_paste' => 'Paste your menu text', 'import_placeholder' => "STARTERS\nBruschetta - 7.50\nSoup of the day - 6\n\nMAINS\nMargherita pizza, tomato and basil - 12", 'import_chars' => ':count of :max characters',
    'import_read' => 'Read my menu', 'import_cost' => 'Costs :count credits', 'import_review' => 'Review', 'import_found' => ':count dishes found',
    'import_no_price' => 'No price found: these dishes are added hidden until you set a price.', 'import_add' => 'Add to my menu', 'import_adding' => 'Adding…',
    'import_done' => 'Added :categories categories and :products dishes. :hidden are hidden until they have a price.', 'import_remove' => 'Remove', 'import_again' => 'Start over',
    'import_not_configured_title' => 'AI is not available yet', 'import_how' => 'How it works',
    'import_step1' => 'Paste the text of your menu.', 'import_step2' => 'AI reads it and groups it. Edit names and prices if needed.', 'import_step3' => 'Add it to your menu in one click.',
    'category' => 'Category', 'dish' => 'Dish name', 'price' => 'Price', 'description' => 'Description',
    'button_import' => 'Import with AI',
    'need_name' => 'Enter the name first.',

    'import_photo' => 'Or a photo of the menu', 'import_photo_cost' => 'A photo costs :count AI credits. Works with printed menus and screenshots.', 'import_pdf' => 'Or a PDF menu', 'import_pdf_hint' => 'PDFs with real text only. For a scanned PDF, take a photo of the page instead.',
    'error_bad_file' => 'That file could not be read. Try a clear JPG, PNG or PDF.', 'error_pdf_no_text' => 'This PDF has no readable text (it is probably a scan). Use the photo import instead.',
    'bulk_title' => 'Translate the whole menu', 'bulk_text' => 'Fills in every missing translation for dishes and categories, in the background. Languages you locked are never touched. Each item uses translation credits.', 'bulk_start' => 'Translate everything missing',
    'bulk_running' => 'Translating…', 'bulk_done' => 'Done: :n texts translated.', 'bulk_stopped' => 'Stopped early (no credits left or the AI service is not available). What was done is kept.',
    'draft_reply' => 'Draft a reply with AI', 'draft_note' => 'Read and edit the draft before you send it.', 'insights_title' => 'Ideas from your numbers', 'insights_button' => 'Get ideas', 'insights_note' => 'Written by an AI from the figures above. Treat them as suggestions.',
    'assistant_name' => 'Menu assistant', 'assistant_ask' => 'Ask about the menu', 'assistant_placeholder' => 'Anything vegetarian? What goes with the burger?', 'assistant_send' => 'Send', 'assistant_hint' => 'Answers come from this menu only. Ask the staff to confirm allergies.', 'assistant_error' => 'The assistant is not available right now.',
];
