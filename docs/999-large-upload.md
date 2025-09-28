# Large Upload (WIP) :

> Reminders to make a doc

```php

$manager = LargeUploadManager::getInstance();

$largeUpload = $manager->start();
// return to client
$identifier = $largeUpload->identifier; 
$storage = $largeUpload->storage;


$largeUpload = $manager->find('some-identifier');
// Add a chunk to the upload
$largeUpload->addChunk(503983, 'some text...')


$userFileStorage = Storage::getInstance()->child('some-file-storage');
$filePath = $largeUpload->wrap($userFileStorage);

$manager->delete('some-identifier');
```