# 📋 WP AI Schema Markup Generator

> Auto-detect content types and generate rich JSON-LD schema markup using AI. Supports Article, FAQ, HowTo, Product, Recipe, LocalBusiness, Course, Event & more.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)
![License](https://img.shields.io/badge/License-GPLv2-green)
![AI](https://img.shields.io/badge/AI-Multi--Provider-orange)
![Schema.org](https://img.shields.io/badge/Schema.org-JSON--LD-red)

---

## ❌ The Problem

- Schema markup gets you **rich snippets** in Google (stars, FAQs, recipes, etc.)
- 90% of site owners don't know how to add it manually
- SEO plugins require you to **manually select** the schema type and fill fields
- Most content has NO schema at all — losing rich snippet opportunities

## ✅ The Solution

This plugin **auto-detects** your content type using smart rules + AI, generates proper JSON-LD schema, and injects it into your pages automatically. Zero configuration needed for basic use.

---

## ✨ Features

- 🧠 **Smart Auto-Detection** — Rule-based + AI detection of content type
- 📋 **11 Schema Types** — Article, FAQ, HowTo, Product, Recipe, LocalBusiness, Course, Event, Review, Video, Software
- ⚡ **Works Without AI** — Rule-based detection works instantly, AI enhances accuracy
- 🤖 **AI Data Extraction** — Extracts FAQ pairs, HowTo steps, Recipe ingredients from content
- 📊 **Coverage Report** — See which pages have schema and which don't
- ⚡ **Bulk Generate** — Add schema to all pages in one click
- 📝 **Editable JSON-LD** — Review and edit schema before saving
- 🔍 **Google Validation Link** — One-click validate with Google Rich Results Test
- 🔌 **5 AI Providers** — OpenAI, Gemini, Claude, OpenRouter, Ollama (all March 2026 models)
- 🆓 **Free Options** — Gemini free tier, OpenRouter free models, Ollama local
- 🛍️ **WooCommerce Support** — Auto-generates Product schema with price, availability, ratings
- 🎓 **LMS Support** — Detects LearnDash, LearnPress, Tutor LMS, LifterLMS courses

---

## 📋 Supported Schema Types

| Type | Auto-Detected When | Rich Snippet |
|------|-------------------|--------------|
| Article | Standard blog posts/pages | Headline, date, author |
| FAQPage | Content has Q&A pairs | FAQ accordion in Google |
| HowTo | "How to..." titles, numbered steps | Step-by-step in Google |
| Product | WooCommerce products | Price, availability, rating |
| Recipe | Ingredients, cook time, servings | Recipe card in Google |
| LocalBusiness | About/Contact pages with address | Business info, hours |
| Course | LMS course post types | Course info in Google |
| Event | Event plugin post types | Date, location in Google |
| Review | Review titles, ratings, pros/cons | Star rating in Google |
| VideoObject | YouTube/Vimeo embeds | Video thumbnail in Google |
| SoftwareApplication | Download, version, changelog | App info in Google |

---

## 🔌 Supported AI Providers 

| Provider | Free Tier | Models | Get Key |
|----------|-----------|--------|---------|
| Google Gemini | ✅ Yes | Gemini 2.0 Flash, 2.5 Pro | [aistudio.google.com](https://aistudio.google.com/apikey) |
| OpenRouter | ✅ Free models | Llama 3.3 70B, Qwen 2.5 72B | [openrouter.ai](https://openrouter.ai/keys) |
| Ollama (Local) | ✅ Free | llama3.3, mistral | [ollama.com](https://ollama.com) |
| OpenAI | ❌ Paid | GPT-4o Mini, GPT-4o, o3-mini | [platform.openai.com](https://platform.openai.com/api-keys) |
| Anthropic Claude | ❌ Paid | Claude 3.5 Haiku, 3.7 Sonnet | [console.anthropic.com](https://console.anthropic.com) |

---

## 🚀 Installation

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/sharanvijaydev/wp-ai-schema-generator.git
```

1. Activate in WordPress → Plugins
2. Go to **AI Schema → Settings**
3. (Optional) Add an AI provider for enhanced detection
4. Set your organization info
5. Go to **AI Schema → Coverage Report** → Scan → Bulk Generate

---

## 📖 Usage

### Automatic Mode (Recommended)
1. Activate the plugin — schema is auto-generated for all pages using rule-based detection
2. No configuration needed for basic Article schema
3. Add AI provider for FAQ/HowTo/Recipe data extraction

### Per-Post Control
1. Edit any post/page
2. Find **📋 AI Schema Markup** in the sidebar
3. Click **🤖 Generate with AI** or **⚡ Quick Generate**
4. Review JSON-LD, edit if needed
5. Click **Save & Activate**
6. Click **🔍 Validate with Google** to test

### Coverage Report
1. Go to **AI Schema → Coverage Report**
2. Click **Scan All Content**
3. See which pages have/lack schema
4. Click **Bulk Generate** to fix all at once
5. Validate individual pages with Google

---

## 📋 Changelog

### 1.0.0
- Initial release
- 11 schema types with auto-detection
- Rule-based + AI-enhanced detection
- AI extraction for FAQ, HowTo, Recipe data
- Coverage report with bulk generation
- Post editor meta box with JSON editor
- Google Rich Results validation link
- Multi-provider AI support (5 providers, March 2026 models)
- WooCommerce Product schema
- LMS Course schema detection

---

## 🗺️ Roadmap

- [ ] Breadcrumb schema
- [ ] Organization schema for homepage
- [ ] Person schema for author pages
- [ ] Schema validation within plugin
- [ ] Import/export schema templates
- [ ] Multi-schema per page (Article + FAQ combined)

---

## 📄 License

GPL v2 or later

## 👨‍💻 Author

**Sharanvijay**
- Website: [thozhilnutpamtech.com](https://thozhilnutpamtech.com)
- GitHub: [@sharanvijaydev](https://github.com/sharanvijaydev)