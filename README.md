mindplay/payload
================

This library lets you encode and decode small data-payloads into strings
that can be safely used in filenames and URLs.

This can be useful for things like encoding resize/cropping-information into
image URLs, creating personalized URLs for sharing things, etc.

The generated strings contain a small checksum as an integrity-check *only* - do
**not** rely on this for "security by obscurity", the data-payload *can be decoded
and is by definition *not* secure in any way.

## Usage

To encode an array as a string:

```php
$string = Payload::encode(["hello" => "world"]); // "cMIDaGVsbG89d29ybGQ"
```

And to decode the string back to an array:

```php
$data = Payload::decode("cMIDaGVsbG89d29ybGQ") // ["hello" => "world"]
```

### Limitations

Only strings and arrays can be encoded. If your data contains integers, these will
be converted to strings, and will arrive in string format when decoded.

### Some Advice

Avoid encoding strings such as filenames, if you can - because the data is
encoded in base64 format, it will increase in size, so a good filename strategy
could be (for example) using an encoded string a prefix or suffix to a filename.
