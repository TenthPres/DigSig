const fs = require('fs');
const path = require('path');

function copyFileSync(source, target) {
    let targetFile = target;

    // If target is a directory, a new file with the same name will be created in it
    if (fs.existsSync(target)) {
        if (fs.lstatSync(target).isDirectory()) {
            targetFile = path.join(target, path.basename(source));
        }
    }

    fs.writeFileSync(targetFile, fs.readFileSync(source));
}

function copyFolderRecursiveSync(source, target) {
    let files = [];

    // Check if folder needs to be created or integrated
    const targetFolder = path.join(target, path.basename(source));
    if (!fs.existsSync(targetFolder)) {
        fs.mkdirSync(targetFolder, { recursive: true });
    }

    // Copy
    if (fs.lstatSync(source).isDirectory()) {
        files = fs.readdirSync(source);
        files.forEach((file) => {
            const curSource = path.join(source, file);
            if (fs.lstatSync(curSource).isDirectory()) {
                copyFolderRecursiveSync(curSource, targetFolder);
            } else {
                copyFileSync(curSource, targetFolder);
            }
        });
    }
}

// Create build directory if it doesn't exist
const buildDir = 'build';
if (!fs.existsSync(buildDir)) {
    fs.mkdirSync(buildDir, { recursive: true });
}

// Define the files and folders to copy
const itemsToCopy = [
    'static',
    'src',
    'DigSig.php',
    'LICENSE.md',
    'composer.json'
];

// Copy each item to the build directory
itemsToCopy.forEach((item) => {
    const fullPath = path.resolve(item);
    if (fs.existsSync(fullPath)) {
        if (fs.lstatSync(fullPath).isDirectory()) {
            copyFolderRecursiveSync(fullPath, buildDir);
        } else {
            copyFileSync(fullPath, path.join(buildDir, path.basename(item)));
        }
    } else {
        console.warn(`Warning: ${item} does not exist and will not be copied.`);
    }
});

console.log('Files and folders have been copied to the build directory.');
