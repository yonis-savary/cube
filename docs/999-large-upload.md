<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>
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
```<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>
