#!/bin/sh

# Get Environment Variables
SALT=$(doppler secrets get SALT --plain)
LICENSE_LIGHTBOX_GALLERY=$(doppler secrets get LICENSE_LIGHTBOX_GALLERY --plain)
INTERN_PWD=$(doppler secrets get INTERN_PWD --plain)
DOMAIN_STG=$(doppler secrets get DOMAIN_STG --plain)
DOMAIN_PRD=$(doppler secrets get DOMAIN_PRD --plain)

# Set Environment Variables in .env.local
rm -f .env.local
touch .env.local
echo "SALT=$SALT" >> .env.local
echo "LICENSE_LIGHTBOX_GALLERY=$LICENSE_LIGHTBOX_GALLERY" >> .env.local
echo "INTERN_PWD=$INTERN_PWD" >> .env.local
echo "DOMAIN_STG=$DOMAIN_STG" >> .env.local
echo "DOMAIN_PRD=$DOMAIN_PRD" >> .env.local
