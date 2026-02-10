#!/bin/bash
set -eu

gcloud pubsub topics publish tigers-result-delivery-event --message='{"command": "batch-update-wishlist"}'
