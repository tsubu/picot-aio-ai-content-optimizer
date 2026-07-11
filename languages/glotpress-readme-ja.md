# WordPress.org プラグインページ — 日本語化手順

[プラグインページ（英語）](https://wordpress.org/plugins/picot-aio-ai-content-optimizer/) の説明文・FAQ などは **SVN の readme.txt ではなく**、[GlotPress（Translate WordPress）](https://translate.wordpress.org/projects/wp-plugins/picot-aio-ai-content-optimizer/) で翻訳・承認された内容が [日本語版ページ](https://ja.wordpress.org/plugins/picot-aio-ai-content-optimizer/) に反映されます。

現状、日本語ページの UI（「説明」「インストール」などの見出し）は日本語ですが、**本文は英語のまま**です。以下の手順で登録してください。

---

## 1. GlotPress にログイン

1. WordPress.org アカウント（**tsubu**）で [translate.wordpress.org](https://translate.wordpress.org/) にログイン
2. プロジェクトを開く:  
   https://translate.wordpress.org/projects/wp-plugins/picot-aio-ai-content-optimizer/
3. 画面上部の言語で **Japanese** を選択（未表示の場合は「Pick a project」から上記 URL へ）

---

## 2. 翻訳を入力

各英文の行をクリックし、対応する日本語を入力します。

**コピペ用データ（推奨）**

| ファイル | 用途 |
|----------|------|
| `glotpress-readme-ja-strings.json` | 原文・訳文の一覧（機械可読） |
| 本ファイル下部 | セクション別の長文コピペ |

入力後 **「Suggest」** または **「Approve」**（PTE 権限がある場合）で保存します。

---

## 3. 承認と反映

| 権限 | できること |
|------|------------|
| 一般翻訳者 | 翻訳を「提案」→ 日本語ロケールの編集者が承認 |
| プラグイン PTE（推奨） | 自分のプラグインの日本語訳を **直接承認** 可能 |

**PTE（Plugin Translation Editor）の申請**

- [Polyglots ハンドブック — PTE](https://make.wordpress.org/polyglots/handbook/plugin-translation-editors/)
- 作者は [Make Polyglots](https://make.wordpress.org/polyglots/) で、プラグイン名と日本語 PTE 希望を投稿

承認後、https://ja.wordpress.org/plugins/picot-aio-ai-content-optimizer/ に **数時間〜24時間** で反映されます。

---

## 4. 確認

- 日本語ページ: https://ja.wordpress.org/plugins/picot-aio-ai-content-optimizer/
- 英語ページ: https://wordpress.org/plugins/picot-aio-ai-content-optimizer/

「説明」「インストール」「FAQ」の本文が日本語になっていれば完了です。

---

## 翻訳一覧（コピペ用）

### 短い説明（プラグイン一覧に表示）

**原文:**
```
AI-powered content optimization using Google Gemini via WordPress AI Client. SEO/AIO advice, one-button rewrite, and AI image generation.
```

**日本語:**
```
WordPress AI Client 経由の Google Gemini による AI コンテンツ最適化。SEO/AIO アドバイス、ワンボタンリライト、AI 画像生成に対応。
```

---

### Description（説明）本文

**原文:**
```
Picot AIO AI Content Optimizer is a WordPress plugin developed by Tsubu (Picot) that elevates your content quality using Google Gemini through the **WordPress AI Client**. It acts as your personal SEO and AIO consultant, providing actionable advice based on Google's Search Quality Rater Guidelines (E-E-A-T).
```

**日本語:**
```
Picot AIO AI Content Optimizer は、Tsubu (Picot)が開発した WordPress プラグインです。WordPress AI Client 経由の Google Gemini でコンテンツ品質の向上を支援し、Google の検索品質評価ガイドライン（E-E-A-T）に基づく、実践的な SEO・AIO アドバイスを提供するパーソナルコンサルタントとして機能します。
```

**Key Features** → **主な機能**

- **E-E-A-T & Quality Guidelines Support** → **E-E-A-T・品質ガイドライン対応**  
  Get precise advice… → 経験・専門性・権威性・信頼性（E-E-A-T）を高めるための的確なアドバイスを取得できます。

- **One-Button SEO/AIO Analysis** → **ワンボタン SEO/AIO 分析**  
  Analyze your existing articles… → 既存記事を分析し、構造化された最適化アドバイスをすぐに取得できます。

- **One-Button Rewrite** → **ワンボタンリライト**  
  Rewrite your articles… → Gemini AI とカスタム指示で記事をリライトできます。

- **AI Image Generation** → **AI 画像生成**  
  Generate and insert… → 投稿に適した高品質な画像を生成して挿入できます。

- **Dual Panel UI** → **デュアルパネル UI**  
  Access the optimizer… → Gutenberg のドキュメントパネルまたはサイドバーから常にアクセスできます。

- **Analysis History** → **分析履歴**  
  Review past analyses… → 投稿ごとの過去の分析結果をエディター内で確認できます。

- **Classic Editor Support** → **クラシックエディター対応**  
  Works in both the block editor and the classic editor. → ブロックエディターとクラシックエディターの両方で利用できます。

---

### External services → 外部サービス

本プラグインは **WordPress AI Client** 経由で AI リクエストを送信し、**Google Gemini コネクターが必須**です。詳細は `glotpress-readme-ja-strings.json` を参照してください。

---

### Installation → インストール

1. Upload the `picot-aio-ai-content-optimizer` folder… → フォルダをアップロードするか、WordPress のプラグイン画面からインストールします。
2. Activate the plugin… → WordPress 7.0 以降が必要です。
3. Install and activate the Google (Gemini) AI connector… → Google AI コネクターをインストールし、**設定 → コネクター** で API キーを接続します。
4. Open Settings → Picot AIO AI Content Optimizer… → Gemini モデルを選択し、投稿エディターで利用します。

---

### FAQ

| 質問（訳） | 回答（訳） |
|------------|------------|
| どの AI コネクターが必要ですか？ | **Google Gemini コネクター**（AI Provider for Google）が必要です。 |
| このプラグインで API キーを入力する必要はありますか？ | いいえ。**設定 → コネクター** で設定してください。 |
| Gemini API キーはどこで取得できますか？ | [Google AI Studio](https://aistudio.google.com/) で取得し、コネクターに登録します。 |
| 無料で使えますか？ | プラグイン本体は無料です。Gemini API の費用は利用量により異なります。 |
| クラシックエディターでも使えますか？ | はい。投稿編集画面のサイドバーにメタボックスが表示されます。 |

---

### Screenshots

1. The Picot AIO panel in the Gutenberg… → Gutenberg 投稿エディターの Picot AIO パネルで SEO/AIO アドバイスを表示。
2. The plugin sidebar accessible… → Gutenberg ツールバーから開けるプラグインサイドバー。
3. Settings page where you configure Gemini models. → Gemini モデルを設定する設定画面。

---

### Changelog / Upgrade Notice

**1.1.0**

- Migrated all AI features to the WordPress AI Client… → すべての AI 機能を WordPress AI Client に移行しました。
- Removed plugin-owned API key settings… → プラグイン内の API キー設定を削除しました。
- Fixed rewrite output being truncated on long articles… → 長文リライトの途中切断を修正しました。
- Updated settings UI for connector-based model selection. → コネクター連携向けに設定 UI を更新しました。
- Migrates to the WordPress AI Client… → WordPress AI Client への移行版です。コネクター設定が必要です。

**1.0.1**

- Raised minimum requirements to WordPress 7.0 and PHP 8.3. → 動作要件を WordPress 7.0 以上・PHP 8.3 以上に更新しました。
- Fixed Classic Editor support for analyze, rewrite, and image suggestions. → クラシックエディタでの分析・リライト・画像提案を修正しました。
- Improved script loading, REST API permissions, and image placement reliability. → スクリプト読み込み、REST API 権限、画像配置の信頼性を改善しました。
- Classic Editor fixes, security improvements, and WordPress 7.0 / PHP 8.3 requirement update. → クラシックエディタ修正、セキュリティ改善、WordPress 7.0 / PHP 8.3 要件更新を含みます。

**1.0.0**

- **Initial release.** → **初回リリース。**
- **Initial version.** → **初版。**

---

## 注意（SVN では不可）

- `readme.txt` を日本語化して SVN に置いても、**wordpress.org のプラグインページ本文は変わりません**（英語 Stable readme が元になります）。
- プラグイン **UI** の日本語は `languages/*.po`（既に同梱）で、サイトの言語設定に従います。
