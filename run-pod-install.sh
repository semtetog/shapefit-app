#!/bin/sh
set -e

cd ios/App
pod install
cd ../..