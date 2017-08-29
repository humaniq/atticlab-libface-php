# Libface-php
Multicurl wrapper for various recognition servers
---
Author: [Atticlab](http://www.atticlab.net/) | GitHub: [Profile](https://github.com/atticlab)
### Installation
---
```sh
$recognition = new \Atticlab\Libface\Recognition($logger);
```

### Enable Kairos
---
Before you start working with kairos, you must register at their service
[Registration](https://github.com/joemccann/dillinger/blob/master/KUBERNETES.md)
After you have to get the keys
```sh
Example
App ID: 8053b393 Key: f0385fae65661043c9ac66d1df3bs804 
```

You also need to pick a name for the gallery we are storing your faces in. We`ve called this "gallery_name". If you had used that gallery name before, we will just add your new face to it, otherwise we will create a new gallery for you.

```sh
$recognition->enableKairos('App ID', 'Key', 'Gallary name'); 
```
