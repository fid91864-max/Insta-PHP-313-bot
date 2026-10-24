/**
 * ============================================================
 *  PROJECT: INSTA INFO 313 — BACKEND API
 *  Developer: Abdullah Al Hasib 313
 *  Telegram: @abdullah_al_hasib_313
 * ============================================================
 */

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET');
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');

  // Extract username from query parameters
  const urlObj = new URL(req.url, `https://${req.headers.host}`);
  const username = req.query?.username || urlObj.searchParams.get('username') || '';
  const cleanUsername = String(username).replace(/[^a-zA-Z0-9._]/g, '').trim();

  if (!cleanUsername) {
    res.statusCode = 400;
    res.end(JSON.stringify({ status: 'error', message: 'ইউজারনেম প্রদান করা হয়নি। দয়া করে সঠিক ইউজারনেম লিখুন।' }, null, 2));
    return;
  }

  if (cleanUsername.length > 30) {
    res.statusCode = 400;
    res.end(JSON.stringify({ status: 'error', message: 'ইউজারনেমটি অত্যন্ত বড়। সঠিক ইউজারনেম প্রদান করুন।' }, null, 2));
    return;
  }

  try {
    const upstreamUrl = `https://i.instagram.com/api/v1/users/web_profile_info/?username=${encodeURIComponent(cleanUsername)}`;
    const response = await fetch(upstreamUrl, {
      headers: {
        "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
        "X-IG-App-ID": "936619743392459",
        "Accept": "*/*",
        "Accept-Language": "en-US,en;q=0.9"
      }
    });

    if (!response.ok) {
      res.statusCode = 404;
      res.end(JSON.stringify({ status: 'error', message: 'প্রোফাইলটি খুঁজে পাওয়া যায়নি অথবা অ্যাকাউন্টটি প্রাইভেট বা সীমিত।' }, null, 2));
      return;
    }

    const json = await response.json();
    const user = json?.data?.user;

    if (!user) {
      res.statusCode = 404;
      res.end(JSON.stringify({ status: 'error', message: 'প্রোফাইল ডাটা সংগ্রহ করা সম্ভব হয়নি।' }, null, 2));
      return;
    }

    const profileData = {
      status: 'success',
      profile: {
        username: user.username || cleanUsername,
        full_name: user.full_name || 'N/A',
        biography: user.biography || '',
        profile_pic_url: user.hd_profile_pic_url_info?.url || user.profile_pic_url_hd || user.profile_pic_url || '',
        profile_url: `https://instagram.com/${user.username || cleanUsername}`,
        category: user.category_name || user.business_category_name || 'Public Intelligence Profile',
        is_verified: Boolean(user.is_verified),
        is_private: Boolean(user.is_private),
        posts_count: user.edge_owner_to_timeline_media?.count || 0,
        followers_count: user.edge_followed_by?.count || 0,
        following_count: user.edge_follow?.count || 0,
        lookup_timestamp: new Date().toISOString()
      },
      developer: {
        name: 'Abdullah Al Hasib 313',
        telegram: '@abdullah_al_hasib_313'
      }
    };

    res.statusCode = 200;
    res.end(JSON.stringify(profileData, null, 2));
  } catch (err) {
    res.statusCode = 500;
    res.end(JSON.stringify({ status: 'error', message: 'সার্ভার সংযোগে অভ্যন্তরীণ ত্রুটি ঘটেছে।' }, null, 2));
  }
}
