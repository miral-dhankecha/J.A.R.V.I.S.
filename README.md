J.A.R.V.I.S: Voice-Enabled Web Assistant (PHP)

J.A.R.V.I.S is a voice-enabled personal assistant that runs in the browser. It listens to spoken commands, replies by voice, and keeps a history and memory of what the user asked. Built as a college project for Web Application Development using PHP (WADUP), BCA, Marwadi University, Rajkot.

Next step: the same core (voice + NLP + assistant logic) is being extended into JARVIS Jammu, a citizen voice assistant for the Jammu Smart City Hackathon.

Screenshots
Home / Dashboard	History	Memory
<img width="1917" height="1078" alt="Screenshot 2026-10-20 104259" src="https://github.com/user-attachments/assets/37be49ea-e329-49ee-b679-702d027d39e8" />
<img width="1917" height="1065" alt="image" src="https://github.com/user-attachments/assets/6d824b64-c1df-47b3-b6d3-bff26a8719f0" />
<img width="1917" height="1078" alt="image" src="https://github.com/user-attachments/assets/33a0068f-6169-47ea-a190-d8ef4a6203d1" />
<img width="1917" height="1078" alt="image" src="https://github.com/user-attachments/assets/684654bb-ecbd-46af-be55-6a338537cbd7" />


Features
Wake word: starts listening after saying "wake up jarvis"
Voice commands and voice replies (speech recognition and text-to-speech)
Weather information for a city (temperature, humidity, wind speed, date, day, time)
Calculator: speak a calculation and get the answer
Open websites and apps by voice command
History: every command is saved and shown in a table
Memory: the assistant remembers things you tell it and recalls them later
Charts: daily activity shown as graphs
Animated GUI with hover effects and a live assistant orb

(Delete any line that is not in your project, and add anything that is.)

Tech Stack
Layer	Technology
Frontend	HTML, CSS, JavaScript
Backend	PHP
Database	MySQL
Data format	JSON
Voice	Web Speech API (browser)
Local server	Laragon / XAMPP
Project Structure
jarvis-assistant/
├── index.php            # main page
├── assets/
│   ├── css/             # stylesheets
│   ├── js/              # scripts
│   └── img/
├── includes/            # PHP helper files
├── database/
│   └── jarvis.sql       # database structure
├── screenshots/
├── config.example.php   # sample config (no real keys)
└── README.md

How to Run Locally
Install Laragon or XAMPP.
Clone this repository into the www (Laragon) or htdocs (XAMPP) folder:
bash
   git clone  https://github.com/miral-dhankecha/J.A.R.V.I.S.
   
   Create a database named jarvis and import database/jarvis.sql.
Copy config.example.php to config.php and enter your own database details and API keys.
Start Apache and MySQL, then open http://localhost/jarvis in Google Chrome.
Allow microphone access, then say "wake up jarvis".

Sample Commands
Say	What happens
"Wake up Jarvis"	Greets you and starts listening
"What is the weather"	Reads out the weather for your city
"Calculate 25 + 17"	Speaks the answer
"Open YouTube"	Opens YouTube in a new tab
"Exit"	Says goodbye and stops listening

Roadmap
 Voice assistant with history, memory and charts
 Multilingual support (Hindi, Dogri, English)
 JARVIS Jammu: citizen complaint and information assistant for Jammu Smart City
 Department dashboard with complaint hotspots

Author
Miral Dhankecha BCA, Marwadi University, Rajkot
Linkdin : www.linkedin.com/in/miral-71b7433b0

License
This project was built for educational purposes.
