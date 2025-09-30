# 🇬🇧 ImgurUK - A Proper British Bodge Job 🇬🇧

> **"Imgur's blocked in the UK? Hold my tea, I'll sort it."** - Claude, probably

## What's All This Then?

Right, so Imgur got itself blocked in the UK (typical), and rather than accept defeat like sensible people, we thought: "Let's let Claude have a go at building an image host." This entire project is a **vibe-coded masterpiece** of questionable decisions and British determination.

This is STRICTLY for laughs. If you're looking for enterprise-grade solutions, you've come to the wrong gaff mate.

## Features (We Think)

- 🖼️ **Image Hosting** - Upload images, get short URLs. Claude insisted on base62 encoding because apparently that's "efficient" or something
- 🔐 **User Authentication** - Claude decided to write an entire auth system LOL. Includes honeypot fields for bots (Claude's quite proud of this one)
- 🌐 **Distributed Proxy Network** - This got wildly out of hand. Users can host proxy scripts on their own servers to help distribute imgur traffic. Claude got VERY excited about this
- 👑 **Admin Panel** - Full user management, image moderation, the works. Claude may have gone slightly overboard
- 🎯 **uk-** Prefix** - All our files start with "uk-" because we're VERY patriotic about our bodged image host

## The Story

**Human:** "Claude, imgur is blocked in the UK, can you help?"

**Claude:** "Sure, let me write a simple proxy—"

**Human:** "Now add user uploads"

**Claude:** "Okay, and I'll add SQLite for—"

**Human:** "Let's add a distributed proxy network where users can contribute bandwidth"

**Claude:** "...I'm going to need to generate custom PHP scripts with API tokens"

**Human:** "Make it so users can register proxies"

**Claude:** "Creating contribute.html..."

**Human:** "Now we need admins"

**Claude:** "Right, full admin panel with user management, image moderation, three-tab interface—"

**Human:** "Perfect, ship it"

## Technical Details (Claude Got Fancy)

### Stack
- **PHP 8.3** - Claude chose this. We don't ask questions
- **SQLite** - "Lightweight and perfect for this," said Claude enthusiastically
- **Nginx** - Because we're not animals
- **Pure CSS** - Not a single npm package was harmed in the making of this site

### Architecture
- `index.php` - Does literally everything. Proxy logic, homepage, the lot
- `auth.php` - Claude's first attempt at authentication (it actually works!)
- `db.php` - Contains the phrase "base62 encoding" which Claude was chuffed about
- `proxy.php` - Generated contributor scripts with API tokens (Claude's magnum opus)
- `admin.php` - Claude may have gotten carried away here
- `upload.php` - Handles the actual image uploads (revolutionary, we know)

### The Proxy System (Claude's Pride and Joy)

Claude invented a distributed CDN where:
1. You request `i.imguruk.com/image.jpg`
2. If it's a `uk-*` file, served locally
3. If not, ImgurUK picks a random contributor proxy
4. That proxy fetches from `i.imgur.com` with proper user agents
5. Returns it to you via ImgurUK
6. Everyone's happy, imgur's rate limits are distributed

Is this necessary? No. Did Claude have fun? Absolutely.

## Security Features (Claude Insisted)

- 🍯 **Honeypot fields** on registration (Claude is very anti-bot)
- 🔒 **bcrypt password hashing** (Claude read the docs)
- 🎫 **API tokens** for proxy authentication
- 🚫 **File type validation** (Claude doesn't trust anyone)
- 👮 **Admin permissions** properly checked everywhere

## How to Use

### For Normal People:
1. Go to the website
2. Register an account
3. Upload images
4. Get a short URL like `i.imguruk.com/uk-a1.jpg`
5. Share with mates

### For Contributors (The Brave):
1. Visit `/contribute.html`
2. Download your personal proxy script (it has YOUR API token in it, fancy that)
3. Upload it to any web-accessible path on your server
4. Register the URL
5. You're now part of the distributed network!
6. Claude thinks you're brilliant

### For Admins (The Powerful):
1. Visit `/admin.html`
2. View all users, images, and proxies
3. Moderate content, manage users, delete things
4. Feel important
5. Try not to abuse your power

## Installation

See the main [README.md](../README.md) in the parent directory for setup instructions. It's surprisingly straightforward for something Claude built.

## Contributing

This was vibe-coded by humans and Claude working together. If you want to contribute:
1. We don't really have a process
2. Just open an issue or PR
3. Claude might review it
4. We'll probably merge it

## The Team

- **daleus** - The human who had the idea and kept asking "but what if..."
- **Claude** - The AI who kept saying "well, technically we could..." and then implementing entire admin panels
- **You** - For actually reading this far down the README

## License

MIT - Do whatever you want with it. If it breaks, that's on you. If it works, Claude will be insufferably pleased with itself.

## A Serious Note (Yes, Really)

While this project is absolutely a joke and was built with more enthusiasm than sense, there's something important underneath the banter:

**Geoblocking is breaking the internet.**

We built this because a popular image hosting service got blocked in an entire country. That's ridiculous. The internet was meant to be open, borderless, and accessible to everyone. When governments and ISPs start carving it up with arbitrary blocks, they're not just inconveniencing people—they're fragmenting something that was supposed to unite us.

This silly little project is our small act of resistance. It's us saying: "The internet should be the internet, not a series of walled gardens determined by your postcode."

Could we have just used a VPN? Sure. But where's the fun in that? And more importantly, why should we have to? Why should anyone have to jump through hoops to access information that's freely available everywhere else?

So yes, this is a joke project. But the question it asks isn't a joke: **How do we keep the internet open when everyone seems determined to close it off?**

We don't have all the answers. But we have a distributed proxy network and an unhealthy amount of optimism. And sometimes, that's enough to start a conversation.

Let the internet be the internet. 🌐

## Disclaimer

This is a joke project. Well, it works, but it started as a joke. It's still kind of a joke. But it's a working joke. Built by an AI who got progressively more enthusiastic about adding features.

If imgur unblocks in the UK, we'll probably keep using this anyway out of spite.

---

**"We asked Claude to solve a simple problem. Claude built a distributed CDN. This is fine."** 🇬🇧☕