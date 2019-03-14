#!/usr/bin/env python3

import datetime
import glob
import logging
import os
import pickle
import requests
import signal
import subprocess
import sys
import time
import traceback
import re
import urllib.parse
import struct

logging.basicConfig(level=logging.DEBUG, filename='bfm_autolauncher.log', filemode='w')
s = requests.Session()
baseurl = "https://bruteforcemovable.com"
currentid = ""
currentVersion = "GITHUB_UPDATE_MIGRATION"
ctrc_kills_al_script = True
active_job = False
os_name = os.name
skipUploadBecauseJobBroke = False

# https://stackoverflow.com/a/16696317 thx
def download_file(url, local_filename):
    # NOTE the stream=True parameter
    r1 = requests.get(url, stream=True)
    with open(local_filename, 'wb') as f1:
        for chunk in r1.iter_content(chunk_size=1024):
            if chunk:  # filter out keep-alive new chunks
                f1.write(chunk)
                # f1.flush() commented by recommendation from J.F.Sebastian
    return local_filename

print("Checking for new release on GitHub...")
githubReleaseRequest = s.get('https://api.github.com/repos/deadphoenix8091/bfm_autolauncher/releases/latest')
if githubReleaseRequest.status_code != 200:
	print("ERROR: Unable to check GitHub for latest release.")
	sys.exit(1)

githubReleaseJson = githubReleaseRequest.json()
print("Updating...")
download_file(githubReleaseJson['assets'][0]['browser_download_url'], 'bfm_seedminer_autolauncher.py')
subprocess.call([sys.executable, "bfm_seedminer_autolauncher.py"])
sys.exit(0)

