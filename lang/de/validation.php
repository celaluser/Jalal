<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':Attribute muss akzeptiert werden.',
    'accepted_if' => ':Attribute muss akzeptiert werden, wenn :other :value ist.',
    'active_url' => ':Attribute muss eine gültige URL sein.',
    'after' => ':Attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => ':Attribute muss ein Datum nach oder gleich dem :date sein.',
    'alpha' => ':Attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':Attribute darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
    'alpha_num' => ':Attribute darf nur Buchstaben und Zahlen enthalten.',
    'any_of' => ':Attribute ist ungültig.',
    'array' => ':Attribute muss ein Array sein.',
    'ascii' => ':Attribute darf nur alphanumerische Single-Byte-Zeichen und Symbole enthalten.',
    'before' => ':Attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => ':Attribute muss ein Datum vor oder gleich dem :date sein.',
    'between' => [
        'array' => ':Attribute muss zwischen :min und :max Elemente enthalten.',
        'file' => ':Attribute muss zwischen :min und :max Kilobytes groß sein.',
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':Attribute muss wahr oder falsch sein.',
    'can' => ':Attribute enthält einen nicht autorisierten Wert.',
    'confirmed' => 'Die Bestätigung von :attribute stimmt nicht überein.',
    'contains' => ':Attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => ':Attribute muss ein gültiges Datum sein.',
    'date_equals' => ':Attribute muss ein Datum gleich dem :date sein.',
    'date_format' => ':Attribute muss dem Format :format entsprechen.',
    'decimal' => ':Attribute muss :decimal Dezimalstellen haben.',
    'declined' => ':Attribute muss abgelehnt werden.',
    'declined_if' => ':Attribute muss abgelehnt werden, wenn :other :value ist.',
    'different' => ':Attribute und :other müssen sich unterscheiden.',
    'digits' => ':Attribute muss aus :digits Ziffern bestehen.',
    'digits_between' => ':Attribute muss zwischen :min und :max Ziffern haben.',
    'dimensions' => ':Attribute hat ungültige Bildabmessungen.',
    'distinct' => ':Attribute enthält einen doppelten Wert.',
    'doesnt_contain' => ':Attribute darf keinen der folgenden Werte enthalten: :values.',
    'doesnt_end_with' => ':Attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':Attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => ':Attribute muss in :encoding kodiert sein.',
    'ends_with' => ':Attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => ':Attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => ':Attribute muss eine Datei sein.',
    'filled' => ':Attribute muss einen Wert haben.',
    'gt' => [
        'array' => ':Attribute muss mehr als :value Elemente enthalten.',
        'file' => ':Attribute muss größer als :value Kilobytes sein.',
        'numeric' => ':Attribute muss größer als :value sein.',
        'string' => ':Attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':Attribute muss mindestens :value Elemente enthalten.',
        'file' => ':Attribute muss mindestens :value Kilobytes groß sein.',
        'numeric' => ':Attribute muss größer oder gleich :value sein.',
        'string' => ':Attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':Attribute muss eine gültige Hexadezimalfarbe sein.',
    'image' => ':Attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => ':Attribute muss in :other vorhanden sein.',
    'in_array_keys' => ':Attribute muss mindestens einen der folgenden Schlüssel enthalten: :values.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'ip' => ':Attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':Attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':Attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':Attribute muss ein gültiger JSON-String sein.',
    'list' => ':Attribute muss eine Liste sein.',
    'lowercase' => ':Attribute muss in Kleinbuchstaben geschrieben sein.',
    'lt' => [
        'array' => ':Attribute muss weniger als :value Elemente enthalten.',
        'file' => ':Attribute muss kleiner als :value Kilobytes sein.',
        'numeric' => ':Attribute muss kleiner als :value sein.',
        'string' => ':Attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':Attribute darf nicht mehr als :value Elemente enthalten.',
        'file' => ':Attribute darf höchstens :value Kilobytes groß sein.',
        'numeric' => ':Attribute muss kleiner oder gleich :value sein.',
        'string' => ':Attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':Attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':Attribute darf nicht mehr als :max Elemente enthalten.',
        'file' => ':Attribute darf nicht größer als :max Kilobytes sein.',
        'numeric' => ':Attribute darf nicht größer als :max sein.',
        'string' => ':Attribute darf nicht länger als :max Zeichen sein.',
    ],
    'max_digits' => ':Attribute darf nicht mehr als :max Ziffern haben.',
    'mimes' => ':Attribute muss eine Datei des Typs :values sein.',
    'mimetypes' => ':Attribute muss eine Datei des Typs :values sein.',
    'min' => [
        'array' => ':Attribute muss mindestens :min Elemente enthalten.',
        'file' => ':Attribute muss mindestens :min Kilobytes groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':Attribute muss mindestens :min Ziffern haben.',
    'missing' => ':Attribute darf nicht vorhanden sein.',
    'missing_if' => ':Attribute darf nicht vorhanden sein, wenn :other :value ist.',
    'missing_unless' => ':Attribute darf nicht vorhanden sein, außer :other ist :value.',
    'missing_with' => ':Attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => ':Attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => ':Attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':Attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':Attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':Attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':Attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Das angegebene :attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes :attribute.',
    ],
    'present' => ':Attribute muss vorhanden sein.',
    'present_if' => ':Attribute muss vorhanden sein, wenn :other :value ist.',
    'present_unless' => ':Attribute muss vorhanden sein, außer :other ist :value.',
    'present_with' => ':Attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => ':Attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => ':Attribute ist nicht erlaubt.',
    'prohibited_if' => ':Attribute ist nicht erlaubt, wenn :other :value ist.',
    'prohibited_if_accepted' => ':Attribute ist nicht erlaubt, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => ':Attribute ist nicht erlaubt, wenn :other abgelehnt wurde.',
    'prohibited_unless' => ':Attribute ist nicht erlaubt, außer :other ist in :values enthalten.',
    'prohibits' => ':Attribute verbietet das Vorhandensein von :other.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':Attribute ist erforderlich.',
    'required_array_keys' => ':Attribute muss Einträge für :values enthalten.',
    'required_if' => ':Attribute ist erforderlich, wenn :other :value ist.',
    'required_if_accepted' => ':Attribute ist erforderlich, wenn :other akzeptiert wurde.',
    'required_if_declined' => ':Attribute ist erforderlich, wenn :other abgelehnt wurde.',
    'required_unless' => ':Attribute ist erforderlich, außer :other ist in :values enthalten.',
    'required_with' => ':Attribute ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all' => ':Attribute ist erforderlich, wenn :values vorhanden sind.',
    'required_without' => ':Attribute ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => ':Attribute ist erforderlich, wenn keines von :values vorhanden ist.',
    'same' => ':Attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => ':Attribute muss :size Elemente enthalten.',
        'file' => ':Attribute muss :size Kilobytes groß sein.',
        'numeric' => ':Attribute muss :size sein.',
        'string' => ':Attribute muss :size Zeichen lang sein.',
    ],
    'starts_with' => ':Attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':Attribute muss ein Text sein.',
    'timezone' => ':Attribute muss eine gültige Zeitzone sein.',
    'unique' => ':Attribute ist bereits vergeben.',
    'uploaded' => ':Attribute konnte nicht hochgeladen werden.',
    'uppercase' => ':Attribute muss in Großbuchstaben geschrieben sein.',
    'url' => ':Attribute muss eine gültige URL sein.',
    'ulid' => ':Attribute muss eine gültige ULID sein.',
    'uuid' => ':Attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
