export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET');
  
  const { username } = req.query;
  const cleanUsername = (username || '').replace(/[^a-zA-Z0-9._]/g, '').trim();

  if (!cleanUsername) {
    return res.status(400).json({ status: 'error', message: 'ইউজারনেম প্রদান করা হয়নি।' });
  }

  try {
    const response = await fetch(`https://i.instagram.com/api/v1/users/web_profile_info/?username=${encodeURIComponent(cleanUsername)}`, {
      headers: {
        "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
        "X-IG-App-ID": "936619743392459",
        "Accept": "*/*",
        "Accept-Language": "en-US,en;q=0.9"
      }
    });

    if (!response.ok) {
      return res.status(404).json({ status: 'error', message: 'প্রোফাইলটি খুঁজে পাওয়া যায়নি অথবা প্রাইভেট অ্যাকাউন্ট।' });
    }

    const data = await response.json();
    const user = data?.data?.user;

    if (!user) {
      return res.status(404).json({ status: 'error', message: 'ইউজার ডাটা পাওয়া যায়নি।' });
    }

    return res.status(200).json({
      status: 'success',
      profile: {
        username: user.username,
        full_name: user.full_name || 'N/A',
        biography: user.biography || '',
        profile_pic_url: user.hd_profile_pic_url_info?.url || user.profile_pic_url_hd || user.profile_pic_url || '',
        profile_url: `https://instagram.com/${user.username}`,
        category: user.category_name || user.business_category_name || 'Public Figure',
        is_verified: !!user.is_verified,
        is_private: !!user.is_private,
        posts_count: user.edge_owner_to_timeline_media?.count || 0,
        followers_count: user.edge_followed_by?.count || 0,
        following_count: user.edge_follow?.count || 0,
        lookup_timestamp: new Date().toISOString()
      },
      developer: {
        name: 'Abdullah Al Hasib 313',
        telegram: '@abdullah_al_hasib_313'
      }
    });
  } catch (error) {
    return res.status(500).json({ status: 'error', message: 'সার্ভার সংযোগে ত্রুটি ঘটেছে।' });
  }
}
